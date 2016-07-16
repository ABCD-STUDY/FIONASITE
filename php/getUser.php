<?php
  session_start(); /// initialize session
  include("AC.php");
  $user_name = check_logged(); /// function checks if visitor is logged.
  if (!$user_name) {
     echo (json_encode ( array( "message" => "no user name" ) ) );
     return; // nothing
  }

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
    if (!check_role( "admin" )) {
       return;
    }

    $pw = ""; // empty password
    if (isset($_POST['pw'])) {
      $pw = $_POST['pw'];
    }
    $fullname = "";
    if (isset($_POST['fullname'])) {
      $fullname = $_POST['fullname'];
    }
    $organization = "";
    if (isset($_POST['organization'])) {
      $organization = $_POST['organization'];
    }

    $id = addUser( $value, $value2, $pw, $fullname, $organization );
    echo(json_encode(array("id" => $id)));
    return;
  } else if ($action == "remove") {
    if (!check_role( "admin" )) {
       return;
    }
    removeUser( $value );
    echo(json_encode(array("message" => "done")));
    return;
  } else if ($action == "changePassword") {
    if ($value != $user_name) {
       return;
    }
    $ok = changePassword( $value, $value2 );
    if ($ok)
      echo(json_encode(array("message" => "done")));
    else
      echo(json_encode(array("message" => "failed")));
    return;
  } else if ($action == "addRole") {
    if (!check_role( "admin" )) {
       return;
    }
    $ret = addRoleToUser( $value, $value2 );
    if ($ret) {
      echo(json_encode(array("message" => "done")));
    } else {
      echo(json_encode(array("message" => "error")));    
    }
    return;
  } else if ($action == "removeRole") {
    if (!check_role( "admin" )) {
       return;
    }
    $ret = removeRoleFromUser( $value, $value2 );
    if ($ret) {
      echo(json_encode(array("message" => "done: ".$ret)));
    } else {
      echo(json_encode(array("message" => "error")));    
    }
    return;
  } else if ($action == "setValue") {
    $ret = setUserVariable( $user_name, $value, $value2 );
    if ($ret) {
      echo(json_encode(array("message" => "done")));
    } else {
      echo(json_encode(array("message" => "error")));    
    }
    return;
  } else if ($action == "getValue") {
    $ret = getUserVariable( $user_name, $value );
    if ($ret) {
      echo( json_encode(array("message" => "done")) ); // value exists
    } else {
      echo( json_encode(array("message" => "error")) );    
    }
    return;
  } else {
    if (!check_role( "admin" )) {
       return;
    }
    echo(json_encode(list_users()));
    return;
  }
 ?>
 