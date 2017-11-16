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

   $config = json_decode(file_get_contents("/data/config/config.json"), TRUE);
   if (isset($config['LOCALTIMEZONE'])) {
     date_default_timezone_set($config['LOCALTIMEZONE']);
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
         $path = '/data/site/archive/scp_'.$key;
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
	    // try to find a good header from these image series - only if we find a good one continue
	    foreach($daics as $da) {
	       $path = pathinfo($da);
	       // get the header for the first file
	       $header = explode($studyInstanceUID, $path['filename'])[0];
	       if ($header !== "" && strpos($header, 'Session') !== false) {
	          break;
	       } else {
	          // reset the header to empty to indicate that we did not find a good enough header
                  $header = "";
               }
            }
            if ($header === "") {
	       echo("Could not get a header from any files for ".$studyInstanceUID.". Skip processing...\n");
               continue;
            } 
            echo("Continue with this header: \"".$header."\" for ".$studyInstanceUID."\n");
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
		      echo("OK, moved over one series ->".$seriesInstanceUID." using header: \"".$header."\"\n");
                  } else {
		      echo("FAILED, moved over one series ->".$seriesInstanceUID." using header: \"".$header."\"\n");
		  }
               } else {
	          echo("Error: NOT ok to move these files ".json_encode($tgz)." with header: \"".$header."\"\n");
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
		for ( $i = 0; $i < count($tgzDAIC); $i++) { // we want to find the correct DAIC file (matches with the series we found in quarantine)
		    $tgzQuarantine = glob('data/quarantine/*'.$seriesInstanceUID.'*.tgz');
		    for ( $j = 0; $j < count($tgzQuarantine); $j++) {
		        if ( filemtime($tgzDAIC[$i]) < filemtime($tgzQuarantine[$j]) ) {
		           $fn = basename($tgzDAIC[$i],'.tgz');
		           // get the header information from the file at the DAIC
		           // we can do this by looking at the part of the filename before the Session
		           $nameparts = explode('Session',$fn);
		           if (count($nameparts) < 2) {
		              echo("Cannot get header information from this file in DAIC ".$fn." [".date('Y-m-d',filemtime($tgzDAIC[$i]))."]. File will not be processed...\n");
		              continue;
		           }
			   $spart = explode('_', $nameparts[1]);
			   if (count($spart) < 2) {
		              echo("Cannot get all header information from this file in DAIC ".$fn." [".date('Y-m-d',filemtime($tgzDAIC[$i]))."]. File will not be processed...\n");
		              continue;			      
			   }
			   $header = $nameparts[0] . "Session" . $spart[0]; // created the new prefix for this file

		           $ofn = basename($tgzQuarantine[$j],'.tgz');

			   // our filename we would like to copy to /data/DAIC/ would be (prepend our header):
			   $newfilename = '/data/DAIC/' . $header . '_' . $ofn . '.tgz';
			   // is this now a new version of the file in DAIC that we would overwrite?
			   if ( $newfilename != $tgzDAIC[$i] ) {
			      // no? ok, don't copy this file over to /data/DAIC, we have the wrong timing information here, try with the next one
			      //echo ("Warning: did not find the correct file in /data/DAIC, should have been: ".$newfilename. ", but got:".$tgzDAIC[$i]."\n");
			      continue;
			   }
			   echo ("Info: did find the same (older) file in /data/DAIC as ".$newfilename." now copy the newer file over to /data/outbox to replace the older file in /data/DAIC");

		           $fs = glob("/".dirname($tgzQuarantine[$j])."/".$ofn."*");
		           if ($force) {
		              foreach ($fs as $f) {
		                  $path_parts = pathinfo($f);
		                  $ok = rename($f, "/data/outbox/".$header.'_'.$ofn.".".$path_parts['extension']);
	  		          if (! $ok) {
			             echo ("Error moving: ".$f. " to /data/outbox/".$header.'_'.$ofn.".".$path_parts['extension']);
	 		          }			  
	                     }		      
		           } else {
		              # create filename for output, should be what we have in DAIC
		              foreach ($fs as $f) {
		                 $path_parts = pathinfo($f);
		                 echo (" should copy newer ".$f." over to outbox/ as ".$header.'_'.$ofn.".".$path_parts['extension']."\n");
	                      }				      
		           }
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
