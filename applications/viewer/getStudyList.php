<?php

   $path = "../../php/raw";
   $files = glob($path. DIRECTORY_SEPARATOR . "*");
   $studyList = array();
   $studyList['studyList'] = array();
   foreach($files as $f) {
      $path_info = pathinfo($f);
      if ( $path_info['filename'] == "." || $path_info['filename'] == "..") {
         continue;
      }
      if (!is_dir($f)) {
         continue;
      }
      // look for one json file inside
      $j = glob($f. DIRECTORY_SEPARATOR . "/*.json");
      if (count($j) > 0) {
        $numImages = 0;
	$numSeries = 0;
        foreach($j as $jf) {
          $data = json_decode(file_get_contents($jf), True);
          if (isset($data['NumFiles'])) {
	     $numImages = $numImages + $data['NumFiles'];
	     $numSeries = $numSeries + 1;
          }
        }
        $data = json_decode(file_get_contents($j[0]), True);
        //echo ("   found study information" . $data['PatientID']);
        $studyList['studyList'][] = array( "patientName" => $data['PatientName'], 
				    	   "patientId" => $data['PatientID'], 
					   "studyDate" => $data['StudyDate'], 
					   "studyTime" => $data['StudyTime'], 
					   "modality" => $data['Modality'], 
					   "studyDescription" => $data['StudyDescription'], 
					   'numImages' => $numImages, 
					   'studyId' => $data['StudyInstanceUID'],
					   'numSeries' => $numSeries);
      }
   }
   // todo: sort by StudyDate and StudyTime
   usort($studyList['studyList'], function($a, $b) {
      if ($a['studyDate'] == $b['studyDate']) {
         return 0;
      } elseif($a['studyDate'] > $b['studyDate']) {
         return -1;
      } else {
         return 1;
      }
   });

   echo (json_encode($studyList, JSON_PRETTY_PRINT));

?>