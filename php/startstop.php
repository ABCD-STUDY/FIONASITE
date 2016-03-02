<?php

  // control file that contains either "0" or "1"
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

  if ($enable == "0") {
    file_put_contents($fn,"0");
  } else {
    file_put_contents($fn,"1");
  }

?>