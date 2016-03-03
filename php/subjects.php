<?php

  $d = '/data/site/raw';
  $results = scandir($d);

  $subjects = array();
  foreach ($results as $result) {
    if ($result === '.' or $result === '..') continue;
    if (is_dir($d . '/' . $result)) {
        $files = glob($d . '/' . $result . '/*/*.json');
	foreach ($files as $f) {
  	  $data = json_decode(file_get_contents($files[0]), True);
	  if ( array_key_exists('PatientID', $data) ) {
            $subjects[] = $data['PatientID'];
	    break; // only check the first non-empty patient ID
	  }
	}
    }
  }
  echo(json_encode($subjects, JSON_PRETTY_PRINT));

?>