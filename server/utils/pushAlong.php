<?php

   //
   // call with -f if you want this to actually do something
   //
   // todo: add check for user processing
   // Run as user processing: 
   //      0 */23 * * * /usr/bin/php /var/www/html/server/utils/pushAlong.php -f >> /var/www/html/server/logs/pushAlong.log
   //    

   $username = exec('whoami');
   if ( $username != "processing" ) {
      echo("Error: run this script as user processing not as ".$username."\n");
      exit(1);
   }

   $shortopts = "f";
   $options = getopt($shortopts);
   $force = false;
   if (isset($options['f'])) {
      $force = true;
   } else {
      $force = false;
   } 

   //
   // get the data we need
   //
   chdir('/var/www/html/php');
   $fname = '/var/www/html/php/getDataFlow.php';
   $data = null;
   if (is_readable($fname)) {
      $data = exec('/usr/bin/php '.$fname);
      $data = json_decode($data, true);
   } else {
     echo("File not readable ".$fname);
   }
   if ($data == null) {
      echo ("No data could be read\n");
      exit(1);
   }

   //
   // lets start by identifying studies that do not have data in raw
   //
   foreach($data as $key => $study) {
      if (array_key_exists('archive', $study) && $study['archive'] == 1 && !array_key_exists('raw',$study)) {
         echo ("found study in archive that is not in raw ".$key."\n");
         $path = 'data/site/archive/scp_'.$key;
         if (is_dir($path)) {
  	    if ($force) {
  	       // send these images using s2m.sh
	       exec('/var/www/html/server/utils/s2m.sh '.$path);
            } else {
	       echo (" run: /var/www/html/server/utils/s2m.sh ".$path."\n");
	    }
         } else {
	    echo (" Error: did not find one archive that fits ".$path."\n");
         }
      }       
   }

   //
   // find studies that have series in DAIC but also some series in quarantine that are not in outbox or DAIC
   //
   foreach($data as $studyInstanceUID => $study) {
      if (isset($study['series'])) {
         $inDAIC = false;
      	 foreach($study['series'] as $seriesInstanceUID => $v) {
	    if (array_key_exists('DAIC',$v) && $v['DAIC'] == 1) {
	       $inDAIC = true;
	       break;
	    }
	 }
	 if (!$inDAIC)
            continue;

	 // if we have an entry in DAIC for this study, we should try to get the correct header
	 $daics = glob('data/DAIC/*'.$studyInstanceUID.'*');
	 $header = "";
	 if ( count($daics) > 0 ) {
	    $path = pathinfo($daics[0]);
	    // get the header for the first file
	    $header = explode($studyInstanceUID, $path['filename'])[0];
         }

         $quarantineButNotOutboxOrDAIC = array();
      	 foreach($study['series'] as $seriesInstanceUID => $v) {
	    if (array_key_exists('quarantine',$v) && $v['quarantine'] == 1) {
	       if ((!array_key_exists('outbox',$v) || $v['outbox'] != 1) && (!array_key_exists('DAIC',$v) || $v['DAIC'] != 1)) {
	          $quarantineButNotOutboxOrDAIC[$seriesInstanceUID] = $study['series'][$seriesInstanceUID];
               }
	    }
         }
	 if (count($quarantineButNotOutboxOrDAIC) > 0) {
	    echo ("\nfound study with some series in DAIC but other not in DAIC yet ".$studyInstanceUID."\n");

	    // we would now try to get the missing series into DAIC as well
	    // This should be a move/rename from quarantine to outbox for tgz,md5sum and json
	    foreach($quarantineButNotOutboxOrDAIC as $seriesInstanceUID => $value) {
	       // all files present?
	       $tgz    = glob('data/quarantine/*'.$seriesInstanceUID.'*.tgz');
	       $md5sum = glob('data/quarantine/*'.$seriesInstanceUID.'*.md5sum');
	       $json   = glob('data/quarantine/*'.$seriesInstanceUID.'*.json');
	       if (count($tgz) > 0 && count($md5sum) > 0 && count($json) > 0 &&
	       	  count($tgz) == count($md5sum) && count($md5sum) == count($json) &&
		  count($tgz) < 3 // should be maximally a DICOM and a k-space tgz for a single series
	       ) {
	          if (!is_writable('data/quarantine') || !is_writable('data/outbox')) {
		     echo ("ERROR: we cannot write into either the source or destination. Permissions wrong?");
		     continue;
		  }

		  if (!$force) {
		     echo("  copy to outbox (header: ".$header."):\n    ".json_encode($tgz)."\n    ".json_encode($md5sum)."\n    ".json_encode($json)."\n");
		     continue;
		  }

	          // move is tricky, start by making a copy, if this works, remove the source
	          $ok = true;
		  foreach($tgz as $t) {
		      $path = pathinfo($t);
		      $destination = "data/outbox/".$header.$path['filename'].".".$path['extension'];
 		      if ($ok) {
		         $ok = copy($t, $destination);
		         if ($ok) { 
			    // now we can remove the file
			    $ok = unlink($t);
			    if (!$ok) {
			       //undo copy
			       unlink($destination);
			    }
		         }
		      } 
		  }
		  foreach($md5sum as $t) {
		      $path = pathinfo($t);
		      $destination = "data/outbox/".$header.$path['filename'].".".$path['extension'];
 		      if ($ok) {
		         $ok = copy($t, $destination);
		         if ($ok) { 
			    // now we can remove the file
			    $ok = unlink($t);
			    if (!$ok) {
			       //undo copy
			       unlink($destination);
			    }
		         }
		      } 
		  }
		  foreach($json as $t) {
		      $path = pathinfo($t);
		      $destination = "data/outbox/".$header.$path['filename'].".".$path['extension'];
 		      if ($ok) {
		         $ok = copy($t, $destination);
		         if ($ok) { 
			    // now we can remove the file
			    $ok = unlink($t);
			    if (!$ok) {
			       //undo copy
			       unlink($destination);
			    }
		         }
		      }		      
		  }
		  if ($ok) {
		      echo("OK, moved over one series ->".$seriesInstanceUID."\n");
                  } else {
		      echo("FAILED, moved over one series ->".$seriesInstanceUID."\n");
		  }
               } else {
	          echo("Error: NOT ok to move these files ".json_encode($tgz)."\n");
               }
	    }
         }
      }
   }

   //
   // find data in DAIC that has a newer version in quarantine (copy new version to outbox for transfer)
   //
   foreach($data as $studyInstanceUID => $study) {
      if (isset($study['series'])) {
      	 foreach($study['series'] as $seriesInstanceUID => $v) {
	    if (array_key_exists('DAIC',$v) && $v['DAIC'] == 1 &&
	        array_key_exists('quarantine',$v) && $v['quarantine'] == 1) {
		// check the file times of both tgz files
		$tgzDAIC = glob('data/DAIC/*'.$seriesInstanceUID.'*.tgz');
		$tgzQuarantine = glob('data/quarantine/*'.$seriesInstanceUID.'*.tgz');
		if (count($tgzDAIC) == 1 && count($tgzQuarantine) == 1 &&
		    filemtime($tgzDAIC[0]) < filemtime($tgzQuarantine[0]) ) {
		   $fn = basename($tgzDAIC[0],'.tgz');
		   $ofn = basename($tgzQuarantine[0],'.tgz');
		   $fs = glob("/".dirname($tgzQuarantine[0])."/".$ofn."*");
		   if ($force) {
		      foreach ($fs as $f) {
		          $path_parts = pathinfo($f);
		          $ok = rename($f, "/data/outbox/".$fn.".".$path_parts['extension']);
			  if (! $ok) {
			     echo ("Error moving: ".$f. " to /data/outbox/".$fn.".".$path_parts['extension']);
			  }			  
	              }		      
		   } else {
		      # create filename for output, should be what we have in DAIC
		      foreach ($fs as $f) {
		          $path_parts = pathinfo($f);
		          echo (" should copy newer ".$f." over to outbox/ as ".$fn.".".$path_parts['extension']."\n");
	              }				      
		   }
                }	       
	    }
	 }
      }
   }

   //
   // find data in DAIC that is also in quarantine (can be deleted)
   //

   //
   // find tgz data in quarantine that does not have an md5sum file (create md5sum file)
   //
   
   //
   // find tgz data in quarantine that has a md5sum file that is older than the tgz (recreate the md5sum file)
   //

?>
