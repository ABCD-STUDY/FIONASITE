<?php

//
// Try to send a notification that image data is going to be send to the DAIC.
// The date is visible in REDCap in the NDA ID (DAIC USE ONLY) instrument as "image_data_send_date".
// 

$pGUID = "";
if (isset($_GET['pGUID'])) {
  $pGUID = $_GET['pGUID'];
} else {
  echo "Error: no pGUID specified";
  return;
}
$project = "";
if (isset($_GET['project'])) {
   $project = $_GET['project'];
}
if ($project == "ABCD") {
   $project = "";
}  

$config = json_decode(file_get_contents('config.json'), TRUE);
$proxy = "";
$proxyport = 3128;
if (isset($config['WEBPROXY'])) {
  $proxy=$config['WEBPROXY'];
  $proxyport=$config['WEBPROXYPORT'];
}
if (isset($config['LOCALTIMEZONE'])) {
  date_default_timezone_set($config['LOCALTIMEZONE']);
}

// get the token from the config file
if (! file_exists("config.json")) {
   echo ("{ \"message\": \"Error: could not read the config file\", \"ok\": \"0\" }");
   return;
}
$configs = json_decode(file_get_contents("config.json"), TRUE);
if (!isset($configs['CONNECTION'])) {
   echo ("{ \"message\": \"Error: could not find CONNECTION setting in the config file\", \"ok\": \"0\" }");
   return;
}
$token = $configs['CONNECTION'];
if ($project != "") { // use this projects token to announce the data
   $token = $configs['SITES'][$project]['CONNECTION'];
}
if ($token == "") {
   echo ("{ \"message\": \"Error: no token found in config file\", \"ok\": \"0\" }");
   return;
}

//
// find out the event for this scan based on the baseline scan date
//

// call redcap to check if a pguid has been consented and the baseline date
function getConsentInfo( $pguid, $token ) {
    global $proxy, $proxyport;

    // consent information is stored at baseline
    $baselineEventName = "baseline_year_1_arm_1";

    // read 2 redcap variables:
    // cp_consent_sign_v2 = Signature of Person Obtaining Informed Consent?
    // cp_timestamp_v2    = Current time
    $args = array(
        'token' => $token,
        'content' => 'record',
        'format' => 'json',
        'type' => 'flat',
        'records' => array($pguid),
        'fields' => array('cp_consent_sign_v2','cp_timestamp_v2'),
        'events' => array($baselineEventName),
        'rawOrLabel' => 'raw',
        'rawOrLabelHeaders' => 'raw',
        'exportCheckboxLabel' => 'false',
        'exportSurveyFields' => 'false',
        'exportDataAccessGroups' => 'false',
        'returnFormat' => 'json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($proxy != "") {
       curl_setopt($ch, CURLOPT_PROXY, $proxy);
       curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
       curl_setopt($ch, CURLOPT_PROXYPORT, $proxyport);
    }
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
    $output = curl_exec($ch);
    $consented = json_decode($output, true)[0]['cp_consent_sign_v2'];
    $baselineDate = json_decode($output, true)[0]['cp_timestamp_v2'];
    curl_close($ch);
    
    return ( array( "consented" => $consented, "baselineDate" => $baselineDate ) );
}

// get the list of events from redcap
function getListOfEvents( $token ) {
    global $proxy, $proxyport;
    
    $args = array(
        'token' => $token,
        'content' => 'event',
        'format' => 'json',
        'returnFormat' => 'json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($proxy != "") {
       curl_setopt($ch, CURLOPT_PROXY, $proxy);
       curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
       curl_setopt($ch, CURLOPT_PROXYPORT, $proxyport);
    }
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
    $output = curl_exec($ch);
    $events = json_decode($output, true);
    curl_close($ch);

    return $events;
}

// get the event name by calculating the number of days between the baseline date and assessment date
function getEventName( $baselineDate, $assessmentDate, $events ) {
    
    // get the number of days between the baseline date and the test date
    $time1 = strtotime($baselineDate);
    $time2 = strtotime($assessmentDate);
    $dateDiff = $time2 - $time1;
    $offset = floor($dateDiff / (60 * 60 * 24));

    // find the event name
    $eventName = "";
    $currEvents = array();
    foreach($events as $event){
        if($event["event_name"] == ".Screener"){
            continue;
        }
        $lower_bound = $event["day_offset"] - $event["offset_min"];
        $upper_bound = $event["day_offset"] + $event["offset_max"];
        if($offset >= $lower_bound && $offset <= $upper_bound){
            $currEvents[] = $event["unique_event_name"];
        }
    }
    if (count($currEvents) == 1) {
        $eventName = $currEvents[0];
    } else {
        echo("ERROR: Not a single event fits this offset: " . $offset . json_encode($events). " (" . date($baselineDate) . " " . $assessmentDate . " " .json_encode($currEvents) . ")\n");
        exit(1);
    }
    return $eventName;

}

$events = getListOfEvents( $token );
$result = getConsentInfo( $pGUID, $token );
$now = date('m/d/Y');
$baselineDate = date_create_from_format('Y-m-d H:i', $result['baselineDate']);
//echo ($baselineDate->format('m/d/Y') . " " . $result['baselineDate']);
$event_name = getEventName( $baselineDate->format('m/d/Y'), $now, $events );

$payload = array(
	 "id_redcap" => $pGUID,
	 "redcap_event_name" => $event_name,
	 "image_data_send_date" => date('Y-m-d H:i')
);

$data = array(
    'token'             => $token,
    'content'           => 'record',
    'format'            => 'json',
    'type'              => 'flat',
    'overwriteBehavior' => 'normal',
    'data'              => '[' . json_encode($payload) . ']',
    'returnContent'     => 'count',
    'returnFormat'      => 'json' 
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
if ($proxy != "") {
   curl_setopt($ch, CURLOPT_PROXY, $proxy);
   curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
   curl_setopt($ch, CURLOPT_PROXYPORT, $proxyport);
}
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 400);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));


$output = curl_exec($ch);
curl_close($ch);
echo ($output);

?>