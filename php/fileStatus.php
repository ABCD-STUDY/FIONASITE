<?php

// calculate what the status of the current scan is
// series instance uid can identify more than one scan
// return an array of detected scans

$filename = "";

$project = "";

if (isset($_GET['project'])) {
  $project = $_GET['project'];
}

if ($project == "ABCD") {
   $project = "";
}

if (isset($_GET['filename'])) {
   $filename = $_GET['filename'];
} else {
  echo ("{ \"ok\": 0, \"message\": \"ERROR: filename not set\" }");
  return;
}

$fn = $filename;
$path_info = pathinfo($filename);

// we can find this study/series in three locations
// we will assume that the naming convention ensures that the series instance uid is in the filename
$q = glob('/data'.$project.'/quarantine/'.$path_info['filename'].".".$path_info['extension']);
$o = glob('/data'.$project.'/outbox/*'.$path_info['filename'].".".$path_info['extension']);
$d = glob('/data'.$project.'/DAIC/*'.$path_info['filename'].".".$path_info['extension']);

// we should check if we have an md5sum file for each one
$qvalid = array();
foreach($q as $f) {
   $path_parts = pathinfo($f);
   // lets see if we have an md5sum file here
   $md5sumfname = $path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.md5sum';
   $jsonfname = $path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.json';
   if (file_exists($md5sumfname)) {
       $qvalid[] = $f;
   }
}

$ovalid = array();
foreach($o as $f) {
   $path_parts = pathinfo($f);
   // lets see if we have an md5sum file here
   $md5sumfname = $path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.md5sum';
   $jsonfname = $path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.json';
   if (file_exists($md5sumfname)) {
       $ovalid[] = $f;
   }
}

$dvalid = array();
foreach($d as $f) {
   $path_parts = pathinfo($f);
   // lets see if we have an md5sum file here
   $md5sumfname = $path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.md5sum';
   $jsonfname = $path_parts['dirname'].DIRECTORY_SEPARATOR.$path_parts['filename'].'.json';
   if (file_exists($md5sumfname)) {
       $dvalid[] = $f;
   }
}

$val = array();
foreach($qvalid as $qv) {
   $val[] = array( "ok" => 1, "message" => "readyToSend", "filename" => $qv );
}
foreach($ovalid as $qv) {
   $val[] = array( "ok" => 1, "message" => "transit", "filename" => $qv );
}
foreach($dvalid as $qv) {
   $val[] = array( "ok" => 1, "message" => "transferred", "filename" => $qv );
}

echo(json_encode($val));

return;

?>
