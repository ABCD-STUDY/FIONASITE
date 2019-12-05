<?php

// calculate what time difference of most recently updated file in same study and current time
// in /data/quarantine folder.
// take study UID  as inpiut

$config = json_decode(file_get_contents('config.json'), TRUE);
if (isset($config['LOCALTIMEZONE'])) {
   date_default_timezone_set($config['LOCALTIMEZONE']);
}

$project = "";

if (isset($_GET['project'])) {
    $project = $_GET['project'];
}

if ($project == "ABCD") {
    $project = "";
}


if (isset($_GET['suid']) && $_GET['suid'] !== "") {
    $suid = $_GET['suid'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"ERROR: suid not set\" }");
    return;
}

$fn = '';
$q = glob('/data'.$project.'/quarantine/'.$suid."*.tgz");
$latestfiletime = 0;

foreach($q as $f) {
    $path_parts = pathinfo($f);
    
    $fmtime = filemtime($f);
     
    if ($fmtime > $latestfiletime) {
         $latestfiletime = $fmtime;
         $fn = $f;
    }
}

//Make sure file change is completed by checking time difference between now and filemtime

$current = date('m/d/Y H:i:s ', time());
//print($current." Current Time : ".strtotime($current));
//print("fileTime: ".$latestfiletime." convert to :".date ("F d Y H:i:s.", $latestfiletime));
$timeDiff = intval(strtotime($current)-$latestfiletime)/60;
//print ($timeDiff);

if ( $latestfiletime > 0 && $timeDiff >  30 ) {	
    $data = [ 'ok' => 1, 'message' => 'readToSend', 'filename'=>$fn];
    //print_r($data);
    //echo ("{ \"ok\": 1, \"message\": \"readToSend\", \"filename\": $fn }");
    echo json_encode($data);
} else {
    echo ("{ \"ok\": 0, \"message\": \"WARNING: filename not Ready to Send yet\" }");
}
       

return;

?>
