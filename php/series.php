<?php

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

  $studies = array();
  foreach ($dirs as $result) {
     $files = glob($d . '/' . $result . '/*.json');
     foreach ($files as $f) {
       $data = json_decode(file_get_contents($f), True);
       if (!array_key_exists($data['StudyInstanceUID'], $studies)) {
	    $studies[$data['StudyInstanceUID']] = [];
       }
       $studies[$data['StudyInstanceUID']][] = $data;
     }
  }
  
  echo(json_encode($studies, JSON_PRETTY_PRINT));

?>