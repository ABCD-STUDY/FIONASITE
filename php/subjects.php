<?php
  // we can only see things that are in our current directory

  $config = json_decode(file_get_contents('config.json'), TRUE);
  if (isset($config['LOCALTIMEZONE'])) {
     date_default_timezone_set($config['LOCALTIMEZONE']);
  }

  $projname = "";
  if (isset($_GET['project'])) {
     $projname = $_GET['project'];
  }

  $d = 'raw';
  if ($projname != "ABCD" && $projname != "") {
      $d = 'raw'.$projname;
  }
  $results = scandir($d);

  # get all files first (need to sort by modification time)
  $dirs = array();
  foreach ($results as $dd) {
    if ($dd === '.' or $dd === '..' or $dd == "")
      continue;
    $dirs[$dd] = filemtime($d."/".$dd);
  }
  asort($dirs);

  $subjects = array();
  foreach ($dirs as $result => $value) {
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
  
  // sort by date and time
  usort($subjects, function($a, $b) {
     $ad = DateTime::createFromFormat( "Ymd His", $a['StudyDate']." ".explode(".",$a['StudyTime'])[0] );
     $bd = DateTime::createFromFormat( "Ymd His", $b['StudyDate']." ".explode(".",$b['StudyTime'])[0] );
     if ($ad == $bd) {
       return 0;
     }
     return $ad < $bd ? -1 : 1;
  });

  echo(json_encode($subjects, JSON_PRETTY_PRINT));

?>
