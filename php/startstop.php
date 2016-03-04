<?php

  // control file that contains something like  "00" or "01" or "10" or "11"
  $fn="/data/enabled";

  // enable disable the system services
  $enable="0";
  if (isset($_GET['enable'])) {
    $enable = $_GET['enable'];
  } else {
    // without a get "enabled" we will just return the current status
    echo(file_get_contents($fn));
    return;
  }
  $val = "00";
  $ar = str_split($enable);
  if (count($ar) > 0) {
    $val[0] = ($ar[0] == "0"?"0":"1");
  }
  if (count($ar) > 1) {
    $val[1] = ($ar[1] == "0"?"0":"1");
  }

  file_put_contents($fn,$val);

?>