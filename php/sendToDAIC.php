<?php

$filename = "";
$id_redcap = "";
$redcap_event_name = "";
$run = "";
$log = '/var/www/html/server/logs/sendToDAIC.log';
$project = "";

$config = json_decode(file_get_contents('config.json'), TRUE);
if (isset($config['LOCALTIMEZONE'])) {
   date_default_timezone_set($config['LOCALTIMEZONE']);
}

if (isset($_GET['project'])) {
   $project = $_GET['project'];
}  
if ($project == "ABCD") {
   $project = "";
}

// find out if we have anonymization enabled
$fn="/data/config/enabled";
if ($project !== "") {
   $fn = '/data'.$project.'/config/enabled';
}
$enable = file_get_contents($fn);
$ar = str_split($enable);
$val = array(0,0,0);
if (count($ar) > 2) {
  $val[2] = ($ar[2] == "0"?"0":"1");
}
$need_to_anonymize = ($val[2]==1);

if (!file_exists($log)) {
   // try to create empty log file
   file_put_contents($log, "");
}

if (isset($_GET['filename'])) {
    $filename = $_GET['filename'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"filename not set\" }");
    return;
}
if (isset($_GET['id_redcap']) && $_GET['id_redcap'] != "") {
    $id_redcap = $_GET['id_redcap'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"id_redcap not set\" }");
    return;
}
if (isset($_GET['redcap_event_name']) && $_GET['redcap_event_name'] != "") {
    $redcap_event_name = $_GET['redcap_event_name'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"redcap_event_name not set\" }");
    return;
}
if (isset($_GET['run']) && $_GET['run'] != "") {
    $run = $_GET['run'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"run not set\" }");
    return;
}

$path_info = pathinfo($filename);

$f = glob('/data'.$project.'/quarantine/'.$path_info['filename'].'.*');
$oksessions = array();
$failedsessions = array();
foreach($f as $fi) {
  $path_parts = pathinfo($fi);
  $destination = '/data'.$project.'/outbox';
  if ($need_to_anonymize) {
     file_put_contents($log, date(DATE_ATOM)." Move file to " . $destination . " now ".$fi." (header: ".$id_redcap."_".$redcap_event_name."_".$run.")\n", FILE_APPEND); 
     $destination = '/data'.$project.'/outbox_anonymize';
     if (!is_dir($destination)) {
        // try to create the directory
	mkdir($destination);
	if (!is_dir($destination)) {
	   file_put_contents($log, date(DATE_ATOM)." Error creating " . $destination . " directory. Permission problem?\n", FILE_APPEND);
           echo("{ \"message\": \"Error, could not create directory: ".$destination."\" }");
	   return; // quit here
	}
     }
  } else {
     file_put_contents($log, date(DATE_ATOM)." Move file to " . $destination . " now ".$fi." (header: ".$id_redcap."_".$redcap_event_name."_".$run.")\n", FILE_APPEND); 
  }
  $prefix = $id_redcap."_".$redcap_event_name."_".$run;
  $ok = rename($fi, $destination.DIRECTORY_SEPARATOR.$prefix."_".$path_parts['filename'].'.'.$path_parts['extension']);
  if (!$ok) {
     $failedsessions[] = $prefix. " " . $fi;
  } else {
     $oksessions[] = $prefix. " " . $fi;
  }
}
echo ("{ \"ok\": 1, \"ok_series\": \"".implode(",",$oksessions)."\", \"failed_series\": \"".implode(",", $failedsessions)."\" }");

return;

?>
