<?php

$action = "getData";
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} 

if ($action == "") {
    echo ("{ \"ok\": 0, \"message\": \"action not set\" }");
    return;
}

$study = "";
if (isset($_GET['study'])) {
    $study = $_GET['study'];
}

if ( $action == "getData" ) {
   $d = "data/quarantine";
   $ar = scandir($d);

   $studies = array();
   foreach( $ar as $key => $value) {
     if (in_array($value, array(".",".."))) {
        continue;
     }
     $studyinstanceuid = "";
     if (strpos($value, "SUID_") == 0) {
     	$a = explode("_", $value);
	if (count($a) > 2) {
          $studyinstanceuid = $a[1];
        }
     } else {
     	$a = explode("_", $value);
	if (count($a) > 1) {
          $studyinstanceuid = $a[0];
        }
     }
     if ($studyinstanceuid == "") {
        // could not detect the study instance UID
	continue;
     }
     if (!isset($studies[$studyinstanceuid])) {
        $studies[$studyinstanceuid] = array( "files" => array(), "size" => 0, "inDAIC" => [], "StudyDate" => "", "PatientID" => "", "PatientName" => "", "header" => "");
        $inDAIC = glob("/data/DAIC/*".$studyinstanceuid."*");
	if (count($inDAIC) > 0) {
	   $studies[$studyinstanceuid]['inDAIC'] = $inDAIC;
	}
	foreach($inDAIC as $f) {
	   $parts = pathinfo($f);
           if ($parts['extension'] == "json") {
	      $data = json_decode(file_get_contents($f),TRUE);
	      if (isset($data["PatientID"])) {
	      	   $studies[$studyinstanceuid]['PatientID'] = $data['PatientID'];
              }
	      if (isset($data["PatientName"])) {
	      	   $studies[$studyinstanceuid]['PatientName'] = $data['PatientName'];
              }
	      if (isset($data["StudyDate"])) {
	      	   $studies[$studyinstanceuid]['StudyDate'] = $data['StudyDate'];
              }
	      
	      break;
           }
        }
	foreach($inDAIC as $f) {
	   $parts = pathinfo($f);
	   if (strpos($parts['filename'], "NDAR") != 0) {
	      continue;
           } 	
           $header = explode("_Session", $parts['filename']);
           $studies[$studyinstanceuid]['header'] = $header[0];
        }

     }
     $fs = filesize($d."/".$value);
     $studies[$studyinstanceuid]['files'][] = $value;
     $studies[$studyinstanceuid]['size'] += $fs;

   }
   echo (json_encode($studies, JSON_PRETTY_PRINT));

}


?>