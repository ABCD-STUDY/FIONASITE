<?php
  session_start(); /// initialize session
  include("AC.php");
  $user_name = check_logged(); /// function checks if visitor is logged.
  if (!$user_name)
     return; // nothing

  $allowed = false;
  if (!check_role( "admin" )) {
     return;
  }

  if (isset($_GET['user_name']))
    $user_name = $_GET['user_name'];
  else
    $user_name = null;

  if (isset($_GET['action']))
    $action = $_GET['action'];
  else
    $action = null;
  if (isset($_GET['value']))
    $value = $_GET['value'];
  else
    $value = null;
  if (isset($_GET['value2']))
    $value2 = $_GET['value2'];
  else
    $value2 = null;
  
  if ($action == "create") {
    $id = addRole( $value );
    echo(json_encode(array("id" => $id)));
    return;
  } else if ($action == "remove") {
    removeRole( $value );
    echo(json_encode(array("message" => "done")));
    return;  
  } else if ($action == "addPermission") {
    addPermissionToRole( $value, $value2 );
    echo(json_encode(array("message" => "done")));
    return;  
  } else {
    echo(json_encode(list_roles($user_name)));
    return;
  }
 ?>
 