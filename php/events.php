<?php

  $config = json_decode(file_get_contents('config.json'), TRUE);
  if (isset($config['LOCALTIMEZONE'])) {
    date_default_timezone_set($config['LOCALTIMEZONE']);
  }
	
  if (isset($_GET["start"]))
    $start = rawurldecode($_GET["start"]);
  else
    $start = null;
  if (isset($_GET["end"]))
    $end = rawurldecode($_GET["end"]);
  else
    $end = null;
  // this is the timezone of the client
  if (isset($_GET["timezone"]))
    $timezone = rawurldecode($_GET["timezone"]);
  else
    $timezone = null;

  $project = "ABCD";
  if (isset($_GET['project'])) {
    $project = $_GET['project'];
  }
  if ($project == "ABCD") {
    $project = "";
  }


  $startdateIn = DateTime::createFromFormat("Y-m-d", $start);
  $enddateIn   = DateTime::createFromFormat("Y-m-d", $end);
  $events = [];

  // add the events that exist on this machine

  // (speedup) Instead of glob which lists all the json files we might get by using readdir and look 
  // at the first two files, we only need a single json from that directory as all StudyTimes should be the 
  // same in all series.
  $files = glob("/data" . $project . "/site/raw/*/*.json");
  $seriesdir = "/data" . $project . "/site/raw/";
  if ($seriesdir_handle = opendir($seriesdir)) {
     while (false !== ($study = readdir($seriesdir_handle))) {
	 $st = $seriesdir.DIRECTORY_SEPARATOR.$study;
         if ($study == "." || $study == ".." || !is_dir($st)) {
	    continue;
 	 }
	 $found = false;
         $firstJSON = "";
	 if ($st_handle = opendir($st)) {
	    // find the first json file in the directory
	    while ( false !== ($series = readdir($st_handle))) {
 	 	$seriespath = $st.DIRECTORY_SEPARATOR.$series;
                if ($series == "." || $series == ".." || is_dir($seriespath)) {
	           continue;
 	        }
	 	// we only want to have files here, check extension
	 	$path_parts = pathinfo($seriespath);
	 	if ($path_parts['extension'] == "json") {
	 	    // found a json file for this series
	 	    $found = true;
		    $firstJSON = $seriespath;
		    break;
	 	}
            }
         }
	 closedir($st_handle);

	 if ($found == true) {
	     $value = $firstJSON;
             $data = json_decode(file_get_contents($value), TRUE);    

             // time could have fractional seconds, use up to seconds only
             $tim = explode(".", $data['StudyTime']);

             $D = DateTime::createFromFormat("Ymd His", $data['StudyDate']. " " . $tim[0]);
             if ($D == null) { // ignore these
	         //syslog(LOG_EMERG, "WE will ignore \"" .$data['StudyDate']. "\" \"" .$data['StudyTime']."\" value:".$value);
                 continue;
             }
             $D2 = $D;
             $D2->add(new DateInterval('PT1H'));
             if ( $D > $startdateIn && $D < $enddateIn ) {
                  $a = array( 'title' => $data['PatientID'], 'start' => $D->format(DateTime::ATOM), 
                              'end' => $D2->format(DateTime::ATOM), 'PatientID' => $data['PatientID'], 
                              'PatientName' => $data['PatientName'], 'StudyInstanceUID' => $data['StudyInstanceUID'] );
                  $events[] = $a;
             }
         } else {
	    // no JSON file in this directory - should be investigated
	    // echo("Warning: no json file in " . $st);
	 }
     }
  }
  closedir($seriesdir_handle);


// old version
/*  foreach($files as $key => $value) {
    if (in_array($value, array(".",".."))) {
       continue;
    }
    $data = json_decode(file_get_contents($value), TRUE);    


    // time could have fractional seconds, use up to seconds only
    $tim = explode(".", $data['SeriesTime']);

    // syslog(LOG_EMERG, "BEFORE in loop, get a json for \"" . $data['SeriesTime'] . "\" => \"" . $tim[0] . "\"" );
    $D = DateTime::createFromFormat("Ymd His", $data['StudyDate']. " " . $tim[0]);
    if ($D == null) { // ignore these
       continue;
    }
    // syslog(LOG_EMERG, "AFTER in loop, get a json for ".$data['PatientID']." date ".$D->format(DATE_ATOM));
    $D2 = $D;
    $D2->add(new DateInterval('PT1H'));
    if ( $D > $startdateIn && $D < $enddateIn ) {
       $a = array( 'title' => $data['PatientID'], 'start' => $D->format(DateTime::ATOM), 
                   'end' => $D2->format(DateTime::ATOM), 'PatientID' => $data['PatientID'], 
                   'PatientName' => $data['PatientName'], 'StudyInstanceUID' => $data['StudyInstanceUID'] );
       $found = false;
       foreach ($events as $ev) {
          if ($ev['StudyInstanceUID'] == $data['StudyInstanceUID']) {
            $found = true;
	    break;
	  }	    
       }
       if (! $found) {
         $events[] = $a;
       }
    }
  } */

// old version
/*
  $d = "/data/scanner";
  $dirs = scandir($d);
  foreach($dirs as $key => $value) {
    if (!in_array($value, array(".",".."))) {
      if (is_file($d . DIRECTORY_SEPARATOR . $value)) {
         //(0040,0244) DA [20160212]                               #   8, 1 PerformedProcedureStepStartDate
         //(0040,0250) DA [20160212]                               #   8, 1 PerformedProcedureStepEndDate
         //(0040,0245) TM [185122]                                 #   6, 1 PerformedProcedureStepStartTime
         //(0040,0251) TM [185857]                                 #   6, 1 PerformedProcedureStepEndTime
 
         // run dcmdump to get the info
         exec('/usr/bin/dcmdump +P 0010,0010 +P 0010,0020 +P 0040,0244 +P 0020,000d +P 0040,0250 +P 0040,0245 +P 0040,0251 '.$d.DIRECTORY_SEPARATOR.$value.' 2>&1', $output);
         //print_r($output);
         $startdate = "";
         $starttime = "";
         $enddate   = "";
         $endtime   = "";
	 $patientID = "";
	 $patientName = "";
	 $studyInstanceUID = "";
	 foreach ($output as $line) {
           preg_match('/\[([^\]]+)/', $line, $match);
           if (count($match) == 0) {
	     continue;
           }
           $m = substr($match[0], 1);

	   if (strpos($line, "StudyInstanceUID") != false) {
	      $studyInstanceUID = $m;
           }
	   if (strpos($line, "PatientID") != false) {
	      $patientID = $m;
           }
	   if (strpos($line, "PatientName") != false) {
	      $patientName = $m;
           }
	   if (strpos($line, "PerformedProcedureStepStartDate") != false) {
	      $startdate = $m;
           }
	   if (strpos($line, "PerformedProcedureStepEndDate") != false) {
	      $enddate = $m;
           }
	   if (strpos($line, "PerformedProcedureStepStartTime") != false) {
	      $starttime = $m;
           }
	   if (strpos($line, "PerformedProcedureStepEndTime") != false) {
	      $endtime = $m;
           }
         }
         $sD = DateTime::createFromFormat("Ymd His", $startdate . " " . $starttime);
         $eD = DateTime::createFromFormat("Ymd His", $enddate . " " . $endtime);
	 // only add these events if they are in the range of $start and $end
         if ( $sD > $startdateIn && $sD < $enddateIn ) {
    	    $events[] = array( 'title' => $patientID, 'start' => $sD->format(DateTime::ATOM), 'end' => $eD->format(DateTime::ATOM), 'PatientID' => $patientID, 'PatientName' => $patientName );
         }
      }
    }
  } */
  echo(json_encode(array_values($events)));

?>