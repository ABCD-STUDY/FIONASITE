<?php

  $d = 'raw';
  $results = scandir($d);
  # get all files first (need to sort by modification time)
  $dirs = array();
  foreach ($results as $dd) {
    if ($dd === '.' or $dd === '..') 
      continue;
    $dirs[$dd] = filemtime($d.DIRECTORY_SEPARATOR.$dd);
  }
  # newest one first  
  arsort($dirs);

  $studies = array();
  foreach ($dirs as $result => $key) {
     $files = glob($d . '/' . $result . '/*.json');
     foreach ($files as $f) {
       $data = json_decode(file_get_contents($f), True);
       //$data['filemtime'] = $key;
       if (!array_key_exists($data['StudyInstanceUID'], $studies)) {
	    $studies[$data['StudyInstanceUID']] = [];
       }
       $studies[$data['StudyInstanceUID']][] = $data;
     }
  }
  
  echo(json_encode($studies, JSON_PRETTY_PRINT));

?>