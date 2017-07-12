<?php
  $project = "";
  if (isset($_GET['project'])) {
     $project = $_GET['project'];
  }
  if ($project == "ABCD") { // default project is just /data/
     $project = "";
  }

  // control file that contains something like  "000"
  $fn="/data/enabled";

  if ($project !== "") {
     $fn = '/data'.$project.'/enabled';
  }

  // enable disable the system services
  $enable="0";
  if (isset($_GET['enable'])) {
    $enable = $_GET['enable'];
  } else {
    // without a get "enabled" we will just return the current status
    echo(file_get_contents($fn));
    return;
  }
  $val = "000";
  $ar = str_split($enable);
  if (count($ar) > 0) {
    $val[0] = ($ar[0] == "0"?"0":"1");
  }
  if (count($ar) > 1) {
    $val[1] = ($ar[1] == "0"?"0":"1");
  }
  if (count($ar) > 2) {
    $val[2] = ($ar[2] == "0"?"0":"1");
  }

  file_put_contents($fn,$val);

?>