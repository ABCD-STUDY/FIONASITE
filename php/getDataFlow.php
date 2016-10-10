<?php

  // 
  // The flow if data throught the FIONA system can be observed by following directory structures for each study and series on the system.
  // This script will capture the current state of the system and produce a hierarchical structure of study/series/state that
  // can be used to visualize the state of the system.
  //
  $data = array();
  chdir('/var/www/html/php');

  // get all studies from /data/site/archive
  $studies = glob('data/site/archive/scp_*', GLOB_ONLYDIR);
  foreach ($studies as $key => $study) {
     $studyInstanceUID = explode("scp_", $study)[1];
     if ($studyInstanceUID != "") {
        $data[$studyInstanceUID] = (object)array( "archive" => 1 );
     }
  }

  // get all studies from data/site/raw
  $studies = glob('data/site/raw/*', GLOB_ONLYDIR);
  foreach( $studies as $key => $study) {
      if ($study != "") {
         $s = basename($study);

	 $series = glob('data/site/raw/'.$s.'/*.json');
         if (count($series) == 0)
             continue;
	 if (!isset($data[$s])) {
	    $data[$s] = (object)array( "raw" => 1 );
         } else {
	    $data[$s]->raw = 1;
 	 }
	 foreach ($series as $serie) {
	    $path_parts = pathinfo($serie);	
	    $ss = $path_parts['filename'];
	    if ($serie != "") {
	      if (!isset($data[$s]->series))
	         $data[$s]->series = array();
              $data[$s]->series[$ss] = array('raw' => 1 );
            }
 	 }	   
      }
  }

  // get all studies from data/quarantine
  $series = glob('data/quarantine/*.tgz');
  foreach( $series as $serie) {
     $sname = basename($serie);

     // what are the study and series instance uids?
     // check if we have k-space data
     if (strpos($sname, "SUID_") === FALSE) {
        // DICOM series
        $path_parts = pathinfo($serie);	
        $sname = $path_parts['filename'];
	
	$s  = explode("_", $sname)[0];	
	$ss = explode("_", $sname)[1];	
	if (!isset($data[$s])) {
           $data[$s] = (object)array( "quarantine" => 1);
        } else {
	   $data[$s]->quarantine = 1;
        }
        if (!array_key_exists('series',$data[$s])) {
	   $data[$s]->series = array();
        }
	if (!array_key_exists($ss,$data[$s]->series)) {
  	   $data[$s]->series[$ss] = (object)array( "quarantine" => 1);
        } else {
           $data[$s]->series[$ss] = (object)array_merge( (array)$data[$s]->series[$ss], array('quarantine' => 1));
        }
     } else {
        // kspace series
	$endstudy = strpos($sname, "_subjid");
	$s = substr($sname, 5, $endstudy-5);
	if (empty($s) || $s == "" || $s === null)
           continue;

	// found a series instance uid, what is the study for this?
        $studyInstanceUID = "";
	foreach($data as $key => $value) {
	   if (isset($value->series)) {
	      foreach($value->series as $k => $se) {
	          if ($k == $se) { // found the series instance uid and the study instance uid
		     $studyInstanceUID = $key;
		     break;
		  }		   
              }
	   }
	   if ($studyInstanceUID != "")
	      break;
	}
	if ($studyInstanceUID == "") {
	   // we can check in the json - if there is one, what the studyInstanceUID should be
	   $path_parts = pathinfo($serie);	
	   $ss = $path_parts['filename'];
	   $j = 'data/quarantine/'.$ss.".json";
	   if (is_readable($j)) {
	      $d = json_decode(file_get_contents($j), true);
	      if (isset($d['StudyInstanceUID'])) {
	         $studyInstanceUID = $d['StudyInstanceUID'];
	      }
	   }
        }
	if ($studyInstanceUID == "") {
	   // this series study is not in raw, it could still be in archive, but its expensive to look there...
	   $studyInstanceUID = uniqid();
	}
	if (!isset($data[$studyInstanceUID])) {
	   $data[$studyInstanceUID] = (object)array( "quarantine" => 1 );
        }

        if (!isset($data[$studyInstanceUID]->series)) {
	   $data[$studyInstanceUID]->series = array();
        }
	if (!array_key_exists($s, $data[$studyInstanceUID]->series)) {
  	   $data[$studyInstanceUID]->series[$s] = (object)array( "quarantine" => 1);
        } else {
	   $data[$studyInstanceUID]->series[$s] = (object)array_merge( (array)$data[$studyInstanceUID]->series[$s], array('quarantine' => 1));
        }

	//if ($s != "") {
        //   $data[$studyInstanceUID]->series[$s] = (object)array_merge( (array)$data[$studyInstanceUID]->series[$s], array('quarantine' => 1));
        //}
     }
  }


  // get all studies from data/outbox
  $series = glob('data/outbox/*.tgz');
  foreach( $series as $serie) {
     $sname = basename($serie);

     // what is the study and series instance uids?
     // check if we have k-space data
     if (strpos($sname, "SUID_") === FALSE) {
        // DICOM series
        $path_parts = pathinfo($serie);	
        $sname = $path_parts['filename'];
	
	// We need the last entry
	$ar = explode("_", $sname);
	$s  = $ar[count($ar)-2];	
	$ss = $ar[count($ar)-1];
	if (!isset($data[$s])) {
           $data[$s] = (object)array( "outbox" => 1); 
       } else {
	   $data[$s]->outbox = 1;
        }
        if (!isset($data[$s]->series)) {
	   $data[$s]->series = array();
        }
	if (!isset($data[$s]->series[$ss])) {
  	   $data[$s]->series[$ss] = (object)array( "outbox" => 1);
        } else {
	   $data[$s]->series[$ss] = (object)array_merge( (array)$data[$s]->series[$ss], array('outbox' => 1));
        }
     } else {
        // kspace series
        $path_parts = pathinfo($serie);	
        $sname = $path_parts['filename'];
	
	$part = explode("SUID_",$sname)[1]; // part after SUID
	$endstudy = strpos($part, "_subjid");
	$s = substr($part, 0, $endstudy);
	// found a series instance uid, what is the study for this?
        $studyInstanceUID = "";
	foreach($data as $key => $value) {
	   if (isset($value->series)) {
	      foreach($value->series as $k => $s) {
	          if ($k == $s) { // found the series instance uid and the study instance uid
		     $studyInstanceUID = $key;
		     break;
		  }		   
              }
	   }
	   if ($studyInstanceUID == "")
	      break;
	}
	if ($studyInstanceUID == "") {
	   // we can check in the json - if there is one, what the studyInstanceUID should be
	   $path_parts = pathinfo($serie);	
	   $ss = $path_parts['filename'];
	   $j = 'data/quarantine/'.$ss.".json";
	   if (is_readable($j)) {
	      $d = json_decode(file_get_contents($j), true);
	      if (isset($d['StudyInstanceUID'])) {
	         $studyInstanceUID = $d['StudyInstanceUID'];
	      }
	   }
        }
	if ($studyInstanceUID == "") {
	   // this series study is not in raw, it could still be in archive, but its expensive to look there...
	   $studyInstanceUID = uniqid();
	}

	if ($s != "") {
	   $data[$studyInstanceUID]->series[$s] = (object)array_merge( (array)$data[$studyInstanceUID]->series[$s], array('outbox' => 1));
	}
     }
  }

  // get all studies from data/outbox
  $series = glob('data/DAIC/*.tgz');
  foreach( $series as $serie) {
     $sname = basename($serie);

     // what is the study and series instance uids?
     // check if we have k-space data
     if (strpos($sname, "SUID_") === FALSE) {
        // DICOM series
        $path_parts = pathinfo($serie);	
        $sname = $path_parts['filename'];
	
	// We need the last entry
	$ar = explode("_", $sname);
	$s  = $ar[count($ar)-2];	
	$ss = $ar[count($ar)-1];
	if (!isset($data[$s])) {
           $data[$s] = (object)array( "DAIC" => 1); 
       } else {
	   $data[$s]->outbox = 1;
        }
        if (!isset($data[$s]->series)) {
	   $data[$s]->series = array();
        }
	if (!isset($data[$s]->series[$ss])) {
  	   $data[$s]->series[$ss] = (object)array( "DAIC" => 1);
        } else {
	   $data[$s]->series[$ss] = (object)array_merge( (array)$data[$s]->series[$ss], array('DAIC' => 1));
        }
     } else {
        // kspace series
        $path_parts = pathinfo($serie);	
        $sname = $path_parts['filename'];
	
	$part = explode("SUID_",$sname)[1]; // part after SUID
	$endstudy = strpos($part, "_subjid");
	$s = substr($part, 0, $endstudy);
	// found a series instance uid, what is the study for this?
        $studyInstanceUID = "";
	foreach($data as $key => $value) {
	   if (isset($value->series)) {
	      foreach($value->series as $k => $se) {
	          if ($k == $se) { // found the series instance uid and the study instance uid
		     $studyInstanceUID = $key;
		     break;
		  }		   
              }
	   }
	   if ($studyInstanceUID == "")
	      break;
	}
	if ($studyInstanceUID == "") {
	   // we can check in the json - if there is one, what the studyInstanceUID should be
	   $path_parts = pathinfo($serie);	
	   $ss = $path_parts['filename'];
	   $j = 'data/quarantine/'.$ss.".json";
	   if (is_readable($j)) {
	      $d = json_decode(file_get_contents($j), true);
	      if (isset($d['StudyInstanceUID'])) {
	         $studyInstanceUID = $d['StudyInstanceUID'];
	      }
	   }
        }
	if ($studyInstanceUID == "") {
	   // this series study is not in raw, it could still be in archive, but its expensive to look there...
	   $studyInstanceUID = uniqid();
	}

	if ($s != "") {
	   $data[$studyInstanceUID]->series[$s] = (object)array_merge( (array)$data[$studyInstanceUID]->series[$s], array('DAIC' => 1));
	}
     }
  }

  echo (json_encode($data));

?>