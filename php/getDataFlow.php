<?php

  // 
  // The flow if data throught the FIONA system can be observed by following directory structures for each study and series on the system.
  // This script will capture the current state of the system and produce a hierarchical structure of study/series/state that
  // can be used to visualize the state of the system.
  //

  $shortopts = "p:";
  $options = getopt($shortopts);
  // project name should be empty (equals to path /data/)
  $project = "";
  if (isset($options['p'])) {
     $project = $options['p'];
  }

  $data = array();
  chdir('/var/www/html/php');

  // get all studies from /data${project}/site/archive
  $studies = glob('data'.$project.'/site/archive/scp_*', GLOB_ONLYDIR);
  foreach ($studies as $key => $study) {
     $studyInstanceUID = explode("scp_", $study)[1];
     if ($studyInstanceUID != "") {
        $data[$studyInstanceUID] = (object)array( "archive" => 1 );
     }
  }

  // get all studies from data/site/raw
  $studies = glob('data'.$project.'/site/raw/*', GLOB_ONLYDIR);
  foreach( $studies as $key => $study) {
      if ($study != "") {
         $s = basename($study);

	 $series = glob('data'.$project.'/site/raw/'.$s.'/*.json');
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
  $series = glob('data'.$project.'/quarantine/*.tgz');
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
	$studyInstanceUID = substr($sname, 5, $endstudy-5);
	$seriesInstanceUID = "";
	// assume that both series and study instance uid's are part of the filename. First entry is study-, second series instance uid.
        preg_match('/.*_([0-9.]*)_.*_([0-9.]*)_.*/', $sname, $matches);
	if (count($matches) > 2) {
	   //echo ("we found two matches in SUID field: ".$matches[1]. " and ". $matches[2]);
	   $seriesInstanceUID = $matches[2];
	}	

	if ($studyInstanceUID == "") {
	   // this series study is not in raw, it could still be in archive, but its expensive to look there...
	   $studyInstanceUID = uniqid();
	}
	if (!isset($data[$studyInstanceUID])) {
	   $data[$studyInstanceUID] = (object)array( "quarantine" => 1 );
        } else {
	  $data[$studyInstanceUID]->quarantine = 1;
        }

        if (!isset($data[$studyInstanceUID]->series)) {
	   $data[$studyInstanceUID]->series = array();
        }

	if ($seriesInstanceUID != "") {
  	   if (!array_key_exists($seriesInstanceUID,$data[$studyInstanceUID]->series)) {
  	      $data[$studyInstanceUID]->series[$seriesInstanceUID] = (object)array( "quarantine" => 1);
           } else {
              $data[$studyInstanceUID]->series[$seriesInstanceUID] = (object)array_merge( (array)$data[$studyInstanceUID]->series[$seriesInstanceUID], array('quarantine' => 1));
           }
        }
     }
  }


  // get all studies from data/outbox
  $series = glob('data'.$project.'/outbox/*.tgz');
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
	$studyInstanceUID = substr($part, 0, $endstudy);

	if ($studyInstanceUID == "") {
	   // this series study is not in raw, it could still be in archive, but its expensive to look there...
	   $studyInstanceUID = uniqid();
	}
	if (!array_key_exists($studyInstanceUID,$data)) {
	  $data[$studyInstanceUID] = (object)array('outbox' => 1);
	} else {
	  $data[$studyInstanceUID]->outbox = 1;
        }
	if (!array_key_exists('series', $data[$studyInstanceUID])) {
	   $data[$studyInstanceUID]->series = array();
        }
     }
  }

  // get all studies from data/DAIC
  $series = glob('data'.$project.'/DAIC/*.tgz');
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
        if (count($ar) < 2)
	   continue;
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
	// this is now the studyInstanceUID, we don't have informations about the series instance uid in here yet
	$studyInstanceUID = substr($part, 0, $endstudy);

	if ($studyInstanceUID == "") {
	   // this series study is not in raw, it could still be in archive, but its expensive to look there...
	   $studyInstanceUID = uniqid();
	}
	// we don't know the series instance uid - should appear soon in the json file, for now just mark that we have some series in DAIC for that study
	if (!array_key_exists($studyInstanceUID,$data)) {
	  $data[$studyInstanceUID] = (object)array('DAIC' => 1);
	} else {
	  $data[$studyInstanceUID]->DAIC = 1;
        }
	if (!array_key_exists('series', $data[$studyInstanceUID])) {
	   $data[$studyInstanceUID]->series = array();
        }

	//if ($s != "") {
	//   $data[$studyInstanceUID]->series[$s] = (object)array_merge( (array)$data[$studyInstanceUID]->series[$s], array('DAIC' => 1));
	//}
     }
  }

  echo (json_encode($data));

?>