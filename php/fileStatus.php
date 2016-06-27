<?php

// calculate what the status of the current scan is

$study = "";
$series = "";

if (isset($_GET['series'])) {
   $series = $_GET['series'];
} else {
  echo ("{ \"ok\": 0, \"message\": \"ERROR: series not set\" }");
  return;
}

if ($series == "") {
  echo ("{ \"ok\": 0, \"message\": \"ERROR: series not set\" }");
  return;
}

// we can find this study/series in three locations
// we will assume that the naming convention ensures that the series instance uid is in the filename
$q = glob('/data/quarantine/*'.$series.'*.tgz');
$o = glob('/data/outbox/*'.$series.'*.tgz');
$d = glob('/data/DAIC/*'.$series.'*.tgz');

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

$status = "acquired"; // everything we are asked for is acquired
if (count($qvalid) > 0) {
   $status = "readyToSend"; // we got a file in quarantine
}
if (count($ovalid) > 0) {
   $status = "transit";
}
if (count($dvalid) > 0) {
   $status = "transferred";
}

echo ("{ \"ok\": 1, \"message\": \"".$status."\" }");

return;

if ($handle = opendir('/data/quarantine')) {
    $found = false;
    // iterate the files in the directory
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            if(!strstr($file, $study)) {
                continue; // skip the files if it does not contain $study
            }
            $found = true;
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 1, \"message\": \"Ready to send\" }");
        return;
    }
}

if ($handle = opendir('/data/outbox')) {
    $found = false;
    // iterate the files in the directory
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            if(!strstr($file, $study)) {
                continue; // skip the files if it does not contain $study
            }
            $found = true;
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 2, \"message\": \"Pending send to DAIC\" }");
        return;
    }
}

if ($handle = opendir('/data/DAIC')) {
    $found = false;
    // iterate the files in the directory
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            if(!strstr($file, $study)) {
                continue; // skip the files if it does not contain $study
            }
            $found = true;
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 3, \"message\": \"Sent to DAIC\" }");
        return;
    } else {
        echo ("{ \"ok\": 0, \"message\": \"ERROR: study not found\" }");
        return;
    }
}

?>
