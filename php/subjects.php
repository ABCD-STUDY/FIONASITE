<?php

  $d = '/data/scratch/views/raw';
  $results = scandir($d);

  $subjects = array();
  foreach ($results as $result) {
    if ($result === '.' or $result === '..') continue;

    if (is_dir($path . '/' . $result)) {
        $subjects[] = $path . '/'. $result;
    }
  }
  echo(json_encode($subjects, JSON_PRETTY_PRINT));

?>