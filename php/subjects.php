<?php
  // we can only see things that are in our current directory

  $d = 'raw';
  $results = scandir($d);
  # get all files first (need to sort by modification time)
  $dirs = array();
  foreach ($results as $dd) {
    if ($dd === '.' or $dd === '..') 
      continue;
    $dirs[filemtime($dd)] = $dd;
  }
  ksort($dirs);

  $subjects = array();
  foreach ($dirs as $result) {
     $files = glob($d . '/' . $result . '/*.json');
     foreach ($files as $f) {
       $data = json_decode(file_get_contents($f), True);
       // we store each patient only once
       if ( array_key_exists('PatientID', $data) ) {
         $subjects[] = $data;
         break; // only check the first non-empty patient ID
       }
     }
  }
  
  echo(json_encode($subjects, JSON_PRETTY_PRINT));

?>