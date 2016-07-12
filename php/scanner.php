<?php

  // get list of subjects from scanner, what is the scanner ID?
  $config = json_decode(file_get_contents('config.json'), TRUE);
  $SCANNERIP = $config['SCANNERIP'];
  $SCANNERAETITLE = $config['SCANNERAETITLE'];
  $SCANNERPORT= $config['SCANNERPORT'];
  $DICOMAETITLE = $config['DICOMAETITLE'];
  $SCANNERTYPE = $config['SCANNERTYPE'];

  $action = "";
  if (isset($_GET['action'])) {
     $action = $_GET['action'];
  }

  // get images for a specific series
  if ($action == "get") {
     $series = "";
     if (isset($_GET['series'])) {
         $series = $_GET['series'];
     }
     if ($series == "" ) {
        echo ("{ \"message\": \"Error, requires series instance uid to work\", \"ok\": \"0\" }");
        return;
     }
     $fname = tempnam('/tmp/', 'movescu');
     file_put_contents( $fname , "# request all images for the seriesinstanceuid\n#\n(0008,0052) CS [SERIES]     # QueryRetrieveLevel\n(0020,000e) UI [".$series."]    # SeriesInstanceUID\n");
     exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/dump2dcm +te '.$fname.' '.$fname.'.dcm');
     exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/movescu -aet '.$DICOMAETITLE." -aec ".$SCANNERAETITLE." --study -aem ".$DICOMAETITLE." ".$SCANNERIP." ".$SCANNERPORT." ".$fname.".dcm");
     // hopefully that will do it
     return;
  }
  // 
  if ($action == "getStudy") {
     $series = "";
     if (isset($_GET['study'])) {
         $study = $_GET['study'];
     }
     if ($study == "" ) {
        echo ("{ \"message\": \"Error, requires study instance uid to work\", \"ok\": \"0\" }");
        return;
     }
     $fname = tempnam('/tmp/', 'movescu');
     file_put_contents( $fname , "# request all images for the studyinstanceuid\n#\n(0008,0052) CS [STUDY]     # QueryRetrieveLevel\n(0020,000d) UI [".$tudy."]    # StudyInstanceUID\n");
     exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/dump2dcm +te '.$fname.' '.$fname.'.dcm');
     exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/movescu -aet '.$DICOMAETITLE." -aec ".$SCANNERAETITLE." --study -aem ".$DICOMAETITLE." ".$SCANNERIP." ".$SCANNERPORT." ".$fname.".dcm");
     // hopefully that will do it
     return;
  }


  function getSeries( $studyinstanceuid ) {
     global $SCANNERIP, $SCANNERAETITLE, $SCANNERPORT, $DICOMAETITLE, $SCANNERTYPE;  

     //findscu -v -aet DAICFIONA -aec CTIPMUCSD2 --study -k 0008,0052=SERIES -k "(0020,000d)=1.2.840.113619.6.374.194029215235239011834696280337568647621"  172.16.132.126 4006
     $lines = array();
     if ($SCANNERTYPE == "SIEMENS") {
       // works for Siemens
       // DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/findscu -aet MGHFIONA -aec BAY4OC --study -k 0008,0052=SERIES -k "(0020,000e)" -k "SeriesDescription" -k "(0020,1002)" -k "(0020,1002)" -k "(0020,000d)=1.3.12.2.1107.5.2.43.67026.30000016070618301156000000113"  172.21.16.64 104
       // 
       exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/findscu -aet '.$DICOMAETITLE.' -aec '.$SCANNERAETITLE.' --study -k 0008,0052=SERIES -k "(0020,000e)=*" -k "SeriesDescription" -k "(0020,1002)" -k "(0020,000d)='.$studyinstanceuid.'" '.$SCANNERIP.' '.$SCANNERPORT.' 2>&1', $lines);
     } elseif ($SCANNERTYPE == "GE") {
       exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/findscu -aet '.$DICOMAETITLE.' -aec '.$SCANNERAETITLE.' --study -k 0008,0052=SERIES -k "(0020,000d)='.$studyinstanceuid.'" '.$SCANNERIP.' '.$SCANNERPORT.' 2>&1', $lines);
     }
     $SeriesInstanceUID = "";
     $ImagesInAcquisition = "";
     $SeriesDescription = "";
     $data = array();
     foreach ($lines as $line) {
        $pattern = '/\[(.*)\]/';
        preg_match($pattern, $line, $matches);
        if (count($matches) == 2) {
           // we got a value
	   if (strpos($line, "SeriesInstanceUID") != FALSE) {
              $SeriesInstanceUID = $matches[1];
              if ($SCANNERTYPE	== "SIEMENS") {
	        // last	entry is series	instance uid, cannot get ImagesInAcquisition for Siemens
                $data[] = array( "ImagesInAcquisition" => $ImagesInAcquisition, "SeriesDescription" => $SeriesDescription, "SeriesInstanceUID" => $SeriesInstanceUID );
      	      }
           }
	   if (strpos($line, "SeriesDescription") != FALSE) {
              $SeriesDescription = $matches[1];
           }
	   // last entry is (0020,1002) IS [3519] #   4, 1 ImagesInAcquisition
	   if (strpos($line, "ImagesInAcquisition") != FALSE) {
              $ImagesInAcquisition = $matches[1];

	      $data[] = array( "ImagesInAcquisition" => $ImagesInAcquisition, "SeriesDescription" => $SeriesDescription, "SeriesInstanceUID" => $SeriesInstanceUID );	      
           }
        }
     }
     return $data;
  }

  // call findscu with the scanner information  
  //echo ('findscu -v -aet DAICFIONA -aec CTIPMUCSD2 --study -k 0008,0052=STUDY 172.16.132.126 4006');
  // --study -k 0008,0052=SERIES -k "(0020,000D)=*" -k "(0020,000E)" -k "(0010,0010)" -k 0008,103E -k "(0010,0020)" -k 0020,0010 -k 0008,0020 -k 0008,0030 -k 0020,1002 

  $lines = array();
  exec('DCMDICTPATH=/usr/share/dcmtk/dicom.dic /usr/bin/findscu -aet '.$DICOMAETITLE.' -aec '.$SCANNERAETITLE.' --study -k 0008,0052=STUDY -k 0008,0030 -k 0020,1002 -k \(0020,000D\)=* -k 0020,0010 -k 0008,0020 -k 0008,0030 -k 0010,0020 -k 0010,0010 '.$SCANNERIP.' '.$SCANNERPORT.' 2>&1', $lines);

  // echo $studies
  // lets scan through all lines and collect information for the current study
  $data = array();
  $StudyInstanceUID = "";
  $PatientID = "";
  $PatientName = "";
  $StudyDate = "";
  $StudyTime = "";
  foreach ($lines as $line) {
      $pattern = '/\[(.*)\]/';
      preg_match($pattern, $line, $matches);
      if (count($matches) == 2) {
         // we got a value
	 if (strpos($line, "PatientID") != FALSE) {
	    $PatientID = $matches[1];
         }
	 if (strpos($line, "PatientName") != FALSE) {
	    $PatientName = $matches[1];
         }
	 if (strpos($line, "StudyInstanceUID") != FALSE) {
	    $StudyInstanceUID = $matches[1];
         }
	 if (strpos($line, "StudyDate") != FALSE) {
	    $StudyDate = $matches[1];
         }
	 if (strpos($line, "StudyTime") != FALSE) {
	    $StudyTime = $matches[1];
         }
	 if (strpos($line, "(0020,0010)") != FALSE) { // last entry
	    $StudyID = $matches[1];
	    // we assume that we got all the entries for this set
	    $d = getSeries( $StudyInstanceUID );
	    $data[] = array( "PatientID" => $PatientID, "PatientName" => $PatientName, 
	    	      	     "StudyInstanceUID" => $StudyInstanceUID, "StudyDate" => $StudyDate, 
			     "StudyTime" => $StudyTime, "Series" => $d);

            // we should ask for the series for this study next
            // findscu -v -aet DAICFIONA -aec CTIPMUCSD2 --study -k 0008,0052=SERIES -k "(0020,000d)=1.2.840.113619.6.374.194029215235239011834696280337568647621"  172.16.132.126 4006

         }
      }
  }
  // sort by date and time
  usort($data, function($a, $b) {
     $ad = DateTime::createFromFormat( "Ymd His", $a['StudyDate']." ".explode(".",$a['StudyTime'])[0] );
     $bd = DateTime::createFromFormat( "Ymd His", $b['StudyDate']." ".explode(".",$b['StudyTime'])[0] );
     if ($ad == $bd) {
       return 0;
     }
     return $ad < $bd ? 1 : -1;
  });

  echo(json_encode($data));

  // if we have a study, ask for the series
?>
