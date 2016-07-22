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

  if (isset($_GET['role']))
    $role = $_GET['role'];
  else
    $role = null;
    
  if (isset($_GET['action']))
    $action = $_GET['action'];
  else
    $action = null;
  if (isset($_GET['value']))
    $value = $_GET['value'];
  else
    $value = null;
  
  if ($action == "create") {
    $id = addPermission( $value );
    echo(json_encode(array("id" => $id)));
    return;
  } else if ($action == "remove") {
    if ($role == "")
       removePermission( $value );
    else
       removePermissionFromRole( $role, $value );
    echo(json_encode(array("message" => "done")));
    return;  
  } else if ($action == "user") {
    echo(json_encode(list_permissions_for_user($value)));
    return;  
  } else {
    echo(json_encode(list_permissions($role)));
    return;
  }  
 ?>
 