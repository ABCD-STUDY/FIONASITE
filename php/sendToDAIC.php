<?php

$filename = "";
$id_redcap = "";
$redcap_event_name = "";
$run = "";
$log = '/var/www/html/server/logs/sendToDAIC.log';

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
if (isset($_GET['redcap_event_name'])) {
    $redcap_event_name = $_GET['redcap_event_name'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"redcap_event_name not set\" }");
    return;
}
if (isset($_GET['run'])) {
    $run = $_GET['run'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"run not set\" }");
    return;
}

$path_info = pathinfo($filename);

$f = glob('/data/quarantine/'.$path_info['filename'].'.*');
$oksessions = array();
$failedsessions = array();
foreach($f as $fi) {
  $path_parts = pathinfo($fi);
  file_put_contents($log, date(DATE_ATOM)." Move file to /data/outbox now ".$fi." (header: ".$id_redcap."_".$redcap_event_name."_".$run.")\n", FILE_APPEND); 
  $prefix = $id_redcap."_".$redcap_event_name."_".$run;
  $ok = rename($fi, '/data/outbox'.DIRECTORY_SEPARATOR.$prefix."_".$path_parts['filename'].'.'.$path_parts['extension']);
  if (!$ok) {
     $failedsessions[] = $prefix. " " . $fi;
  } else {
     $oksessions[] = $prefix. " " . $fi;
  }
}
echo ("{ \"ok\": 1, \"ok_series\": \"".implode(",",$oksessions)."\", \"failed_series\": \"".implode(",", $failedsessions)."\" }");

return;

?>
