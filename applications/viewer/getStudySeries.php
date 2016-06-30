<?php

    function curPageURL() {
      $pageURL = 'http';
      if (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
      $pageURL .= "://";
      if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"]."/applications/viewer/";
      } else {
        $pageURL .= $_SERVER["SERVER_NAME"]."/applications/viewer/";
      }
      return $pageURL;
    }


   $studyinstanceuid = "1.2.840.113619.6.374.123785480713183678826326145925137728378";
   if (isset($_GET['StudyInstanceUID'])) {
      $studyinstanceuid = $_GET['StudyInstanceUID'];
   } else {
      echo ("Error: no StudyInstanceUID supplied");
      //return;
   }

   $path = "../../php/data/site/raw";
   $files = glob($path. DIRECTORY_SEPARATOR . $studyinstanceuid . "/*.json");
   $seriesList = array();
   $seriesList['modality'] = "MR";
   $seriesList['seriesList'] = array();
   $url = curPageURL();
   foreach($files as $f) {
     $data = json_decode(file_get_contents($f), True);
     if (!isset($seriesList['patientName'])) { // enter these values for the first time
        $seriesList['patientName'] = $data['PatientName'];
	$seriesList['patientId'] = "";
	$seriesList['studyDate'] = "";
	$seriesList['numImages'] = "";
	$seriesList['studyId']   = "";
	$seriesList['studyDescription']   = "";
	if (isset($data['PatientId'])) {
	   $seriesList['patientId'] = $data['PatientId'];
        }
	if (isset($data['StudyDate'])) {
  	   $seriesList['studyDate'] = $data['StudyDate'];
        }
	if (isset($data['NumFiles'])) {
	   $seriesList['numImages'] = $data['NumFiles'];
        }
	if (isset($data['StudyDescription'])) {
	   $seriesList['studyDescription']   = $data['StudyDescription'];
        }
	if (isset($data['StudyDescription'])) {
	   $seriesList['studyId']   = $data['StudyDescription'];
        }
     }
     $instanceList = array();
     // fill the instance list now with data from that directory
     $dicoms = glob($path . DIRECTORY_SEPARATOR . $studyinstanceuid . DIRECTORY_SEPARATOR . $data['SeriesInstanceUID'] . DIRECTORY_SEPARATOR . "*");
     foreach($dicoms as $d) {
        $path_info = pathinfo($d);
        if ($path_info['filename'] == "." || $path_info['filename'] == "..") {
           continue;
        }
        if (count($instanceList) > 200) {
            break;
        }
        $instanceList[] = array( "imageId" => $url . $d );
     }
     $seriesList['seriesList'][] = array( "seriesDescription" => $data['SeriesDescription'], "seriesNumber" => $data["SeriesNumber"], "instanceList" => $instanceList );
   }

   echo (json_encode($seriesList, JSON_PRETTY_PRINT));

?>
