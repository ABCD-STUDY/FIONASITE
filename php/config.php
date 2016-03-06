<?php

  // use a symbolic link to the current directory to place /data/config/config.json there
  $config = "config.json";

  if (!is_readable($config)) {
    echo("{ \"message\": \"config file is not readable by the web user\" }");
    return;
  }

  $action = "list";
  if (isset($_GET['action'])) {
    $action = $_GET['action'];
  }

  if ($action == "list") {
    echo(file_get_contents($config));
    return;
  } else if ($action == "save") {
    if (!isset($_GET['value'])) {
      echo("{ \"message\": \"no value specified\" }");
      return;
    }
    $value = json_decode($_GET['value'], true);
    file_put_contents($config, json_encode($value, JSON_PRETTY_PRINT)); 
    return;
  } else {
    echo("{ \"message\": \"this operation is not supported\" }");
    return;
  } 

?>