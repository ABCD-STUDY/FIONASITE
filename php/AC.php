<?php
 #
 # Access Control Code using access control lists defined in passwords.json
 #
 # ToDo: implement digest based authentication
 #

 date_default_timezone_set('America/Los_Angeles');

 $pw_file = "/var/www/html/php/passwords.json";
 $audit_file = "/var/www/html/server/logs/audit.log";
 $audit_on = 1;

  function audit( $what, $message ) {
    global $audit_file, $audit_on;

    if (!$audit_on)
       return;

    if (!is_writable( $audit_file )) {
       return;
    }
    $e = new Exception;
    $callers = $e->getTraceAsString();
    $callers = explode("\n", $callers);
    if (strpos( $callers[1], "/var/www/html/php/AC.php" ) !== FALSE)
       return; // called by myself, don't add

    $str = "[".date(DATE_RFC2822)."] [".$callers[1]."] ".$what.": ".trim(preg_replace('/\s+/', ' ', $message))."\n";
    file_put_contents( $audit_file, $str, FILE_APPEND );
  }

  function loadDB() {
     global $pw_file;

     // parse permissions
     if (!file_exists($pw_file)) {
        echo ('error: permission file does not exist');
        return;
     }
     if (!is_readable($pw_file)) {
        echo ('error: cannot read file...');
        return;
     }
     $d = json_decode(file_get_contents($pw_file), true);
     if ($d == NULL) {
        echo('error: could not parse the password file');
     }

     return $d;
  }

  function saveDB( $d ) {
     global $pw_file;

     // parse permissions
     if (!file_exists($pw_file)) {
        echo ('error: permission file does not exist');
        return;
     }
     if (!is_writable($pw_file)) {
        echo ('Error: cannot write permissions file ('.$pw_file.')');
        return;
     }
     // be more careful here, we need to write first to a new file, make sure that this
     // works and copy the result over to the pw_file
     $testfn = $pw_file . '_test';
     file_put_contents($testfn, json_encode($d));
     if (filesize($testfn) > 0) {
        // seems to have worked, now rename this file to pw_file
	rename($testfn, $pw_file);
     } else {
        syslog(LOG_EMERG, 'ERROR: could not write file into '.$testfn);
     }
  }

  // returns a positive id of the new role if everything worked
  function addRole( $name ) {
    $d = loadDB();
    
    $found = false;
    $highestID = 0;
    foreach ($d['roles'] as $role) {
       if ($name == $role['name']) {
          $found = true;
       }
       if ($role['id'] > $highestID)
           $highestID = $role['id'];
    }
    $highestID++;
    if (!$found) {
      array_push( $d['roles'], array( "name" => $name, "id" => $highestID, "permissions" => array() ) );
      saveDB( $d );
    } else {
      $highestID = -1; // indicate error
    }
    audit( "addRole", $name );
    return $highestID;
  }

  // returns a positive id of the new role if everything worked
  function removeRole( $name ) {
    $d = loadDB();
    
    $found = false;
    foreach ($d['roles'] as $key => $role) {
       if ($name == $role['name']) {
          unset($d['roles'][$key]);
          $found = true;
       }
    }
    if ($found) {
      audit( "removeRole", $name );
      saveDB( $d );
    }
  }

  // returns a positive id of the new role if everything worked
  function addUser( $name, $email, $pw, $fullname, $organization ) {
    $d = loadDB();
    
    $found = false;
    $highestID = 0;
    foreach ($d['users'] as $user) {
       if ($name == $user['name']) {
          $found = true;
       }
       if ($email == $user['email']) {
          $found = true;
       }
       if ($user['id'] > $highestID)
           $highestID = $user['id'];
    }
    $highestID++;
    if (!$found) {
      array_push( $d['users'], array( "name" => $name, "id" => $highestID, "password" => $pw, "email" => $email, "fullname" => $fullname, "organization" => $organization, "roles" => array() ) );
      saveDB( $d );
    } else {
      $highestID = -1; // indicate error
    }
    audit( "addUser", $name." ".$email." ".$fullname." ".$organization );
    return $highestID;
  }
  // returns a positive id of the new role if everything worked
  function changePassword( $name, $pw ) {
    $d = loadDB();
    
    $found = false;
    foreach ($d['users'] as &$user) {
       if ($name == $user['name'] || $name == $user['email']) {
          $user["password"] = $pw;
          saveDB( $d );
          $found = true;
          break;
       }
    }
    if (!$found)
       return FALSE;
    audit( "changePassword done", $name );
    return TRUE;
  }

  // returns a positive id of the new role if everything worked
  function removeUser( $name ) {
    $d = loadDB();
    
    $found = false;
    foreach ($d['users'] as $key => $user) {
       if ($name == $user['name'] || $name == $user['email']) {
          unset($d['users'][$key]);
          $found = true;
       }
    }
    if ($found) {
      audit( "removeUser done", $name );
      saveDB( $d );
    }
  }

  // returns success
  function setUserVariable( $name, $valuekey, $valuevalue ) {

    //file_put_contents("/tmp/bla", json_encode( array( "user" => $name ) ) );
    $d = loadDB();
    
    $found = false;
    foreach ($d['users'] as $key => $user) {
       if ( strcmp($name, $user['name']) == 0 || 
            strcmp($name, $user['email']) == 0 ) {
          if (strcmp($valuevalue, "rm") == 0) {
             unset($d['users'][$key][$valuekey]);
          } else {
   	     $d['users'][$key][$valuekey] = $valuevalue;
          }
          $found = true;
       }
    }
    if ($found) {
      saveDB( $d );
      return true;
    }
    return false;
  }

  // returns a positive id of the new role if everything worked
  function getUserVariable( $name, $valuekey ) {
    $d = loadDB();
    
    $found = false;
    foreach ($d['users'] as $key => $user) {
       if (strcmp($name, $user['name']) == 0 || 
           strcmp($name, $user['email']) == 0 ) {
          //unset($d['users'][$key]);
          if (array_key_exists($valuekey, $d['users'][$key])) {
  	    return $d['users'][$key][$valuekey];
          } else
	    return FALSE;
       }
    }
    return FALSE;
  }
  
  function addPermissionToRole( $permission, $role ) {
    $d = loadDB();
    
    $permission_id = -1;
    foreach ($d['permissions'] as $key => $value) {
       if (strcmp($value['name'], $permission) == 0) {
          $permission_id = $value['id'];
       }
    }
    if ($permission_id == -1) {
       return false; // unknown role  
    }  
    
    foreach ($d['roles'] as $key => $u) {
       if ($role == $u['name']) {
          $found = false;
          foreach ($u['permissions'] as $p) {
             if ($permission_id == $p) {
                $found = true;
             }
          }
          if (!$found) {
             $d['roles'][$key]['permissions'][] = $permission_id;
             saveDB( $d );
             return true;
          }
       }
    }

    return false;  

  }
  
  function addRoleToUser( $role, $user ) {
    $d = loadDB();
    
    $role_id = -1;
    foreach ($d['roles'] as $key => $value) {
       if (strcmp($value['name'], $role) == 0) {
          $role_id = $value['id'];
       }
    }
    if ($role_id == -1) {
       return false; // unknown role  
    }
    
    foreach ($d['users'] as $key => $u) {
       if ($user == $u['name']) {
          $found = false;
          foreach ($u['roles'] as $r) {
             if ($role_id == $r) {
                $found = true;
             }
          }
          if (!$found) {
             $d['users'][$key]['roles'][] = $role_id;
             saveDB( $d );
             return true;
          }
       }
    }

    return false;  
  }
  
  function removeRoleFromUser( $role, $user ) {
    $d = loadDB();

    $role_id = -1;
    foreach ($d['roles'] as $key => $value) {
       if (strcmp($value['name'], $role) == 0) {
          $role_id = $value['id'];
       }
    }
    if ($role_id == -1) {
       return false; // unknown role                                                                                                                                                                
    }

    foreach ($d['users'] as $key => $u) {
       if ($user == $u['name']) {
         if (in_array($role_id, $u['roles'])) {
             $d['users'][$key]['roles'] = array_diff($d['users'][$key]['roles'], array( $role_id ));
             saveDB( $d );
             return true;
         }
       }
    }

    return false;
  }
  
  // returns a positive id of the new role if everything worked
  function addPermission( $name ) {
    $d = loadDB();
    
    $found = false;
    $highestID = 0;
    foreach ($d['permissions'] as $perm) {
       if ($name == $perm['name']) {
          $found = true;
       }
       if ($perm['id'] > $highestID)
           $highestID = $perm['id'];
    }
    $highestID++;
    if (!$found) {
      array_push( $d['permissions'], array( "name" => $name, "id" => $highestID ) );
      saveDB( $d );
      audit( "addPermission", $name );
    } else {
      $highestID = -1; // indicate error
    }
    return $highestID;
  }

  // remove permission
  function removePermission( $name ) {
    $d = loadDB();
    
    $found = false;
    foreach ($d['permissions'] as $key => $perm) {
       if ($name == $perm['name']) {
          unset($d['permissions'][$key]);
          $found = true;
       }
    }
    if ($found) {
      saveDB( $d );
      audit( "removePermission", $name );
    }
  }
  
  // remove permission
  function removePermissionFromRole( $roleName, $name ) {
    $d = loadDB();
    $id = -1;
    // what is the number of the permission?
    foreach ($d['permissions'] as $key => $perm) {
       if ( $perm['name'] == $name ) {
          $id = $perm['id'];
          break;
       }
    }
    if ($id == -1) {
       return;
    }
    
    $found = false;
    foreach ($d['roles'] as $key => $role) {
       if ($role['name'] != $roleName)
         continue;
syslog(LOG_EMERG, 'try to remove permission '.$name.' ('.$id.') from '.$role['name']);
       if (in_array($id, $role['permissions'])) {
            $d['roles'][$key]['permissions'] = array_diff($d['roles'][$key]['permissions'], array( $id ));
            //unset($d['roles'][$name]);
            $found = true;
       }
    }
    if ($found) {
      saveDB( $d );
      audit( "removePermissionFromRole", $roleName." ".$name );
    }
  }
  // set the global USER array with user and passwords
  global $USERS;
  $d = loadDB();

  $USERS = array();
  foreach ( $d["users"] as $value ) {
    $USERS[ $value["name"] ] = $value["password"];
    if ( array_key_exists("email", $value) ) {
       $USERS[ $value["email"] ] = $value["password"];
    }
  }

  // does the user has a specific role?
  function check_role( $role_in ) {
    global $_SESSION;

    // read the permissions database
    $d = loadDB();

    // check if the current user
    $user_name = $_SESSION["logged"];
    // has a specified role
    $roles = array(); // collect all roles for this user
    foreach ( $d["users"] as $key => $value ) {
      if ($value["name"] == $user_name) {
         $roles = array_merge($roles, $value["roles"]);
      }
    }
    
    foreach ( $roles as $role ) {
       if ($role == $role_in) {
           audit( "check_role", $role_in." as ".$user_name);
           return true;
       }
    }
    audit( "check_role failed", $role_in." as ".$user_name);
    return false;
  }

  function getUserNameFromEmail( $email ) {
    // read the permissions database
    $d = loadDB();

    foreach ( $d["users"] as $key => $value ) {
      if ($value["email"] == $email) {
         return $value["name"];
      }
    }
    return "unknown";
  }

  // does the user has permissions?
  function check_permission( $permission ) {
    global $_SESSION;

    // read the permissions database
    $d = loadDB();

    // check if the current user
    $user_name = $_SESSION["logged"];
    // is allowed to use permission
    $roles = array();
    foreach ( $d["users"] as $key => $value ) {
      if ($value["name"] == $user_name) {
         $roles = array_merge($roles, $value["roles"]);
      }
    }

    // for each role find the list of permissions
    $userpermissions = array();
    foreach ($d["roles"] as $key => $value) { // all known roles      
        foreach ($roles as $role) { // roles of the current user
           if ($value["id"] == $role) {
             $userpermissions = array_merge($userpermissions, $value["permissions"]);
           }
        }
    }
    // print_r($userpermissions);
    
    // for each found permission find the name and compare to requested permission
    foreach ($userpermissions as $perm) {
       foreach ($d["permissions"] as $key => $value) {
           if ($perm == $value["id"] && 
               $value["name"] == $permission) {
              audit( "check_permission", $permission." as ".$user_name);
              return true;
           }
       }
    }

    audit( "check_permission failed", $permission." as ".$user_name);
    return false;
  }
  // has to be logged in, forwards to login page if not
  function check_logged() {
     global $_SESSION, $USERS, $_SERVER;

     if (!array_key_exists($_SESSION["logged"],$USERS)) {
      	$qs = $_SERVER['QUERY_STRING'];
        audit( "check_logged failed", "" );
        if ($qs != "")
           header("Location: /applications/User/login.php".$_SERVER['QUERY_STRING']."&url=".$_SERVER['PHP_SELF']);
        else
           header("Location: /applications/User/login.php"."?url=".$_SERVER['PHP_SELF']);
     };
     // store that this user has logged in now
     setUserVariable( $_SESSION["logged"], "lastTimeLoggedIn", date(DATE_RFC2822) );

     audit( "check_logged", " as ".$_SESSION["logged"] );
     return $_SESSION["logged"];
  };

  // list all users, secure function, returns nothing if user is not logged in or
  // role is not admin
  function list_users() {
    session_start(); /// initialize session
    $user_name = check_logged(); /// function checks if visitor is logged in.
    if (!$user_name)
       return;

    $allowed = false;
    if (!check_role( "admin" )) {
        return false;
    }

    // read the permissions database
    $d = loadDB();
    return $d["users"];
  }

  // list all users, secure function, returns nothing if user is not logged in or
  // role is not admin
  function list_roles( $user_in ) {
    session_start(); /// initialize session
    $user_name = check_logged(); /// function checks if visitor is logged in.
    if (!$user_name)
       return;

    $allowed = false;
    if (!check_role( "admin" )) {
        return false;
    }

    // read the permissions database
    $d = loadDB();
    if ($user_in !== null) { // return role names of the current user
      foreach ($d["users"] as $key => $value) {
         if ( $value["name"] == $user_in ) {
            $role_names = array();
            foreach ($value["roles"] as $role) {
               foreach ($d["roles"] as $r) {
                 if ($role == $r["id"])
                   $role_names[] = $r["name"];
               }
            }
            return $role_names;
         }
      }
    } else { // return all role names
      $role_names = array();
      foreach ($d["roles"] as $r) {
         $role_names[] = $r['name'];
      }
      return $role_names;
    }
    return;
  }

  // list all users, secure function, returns nothing if user is not logged in or
  // role is not admin
  function list_permissions( $role_in ) {
    global $_SESSION;
    if (!isset($_SESSION)) {
      session_start();
    }
    $user_name = check_logged(); /// function checks if visitor is logged in.
    if (!$user_name)
       return;

    $allowed = false;
    if (!check_role( "admin" )) {
        return false;
    }

    // read the permissions database
    $d = loadDB();
    if ($role_in !== null) { // return role names of the current user
      foreach ($d["roles"] as $key => $value) {
         if ( $value["name"] == $role_in ) {
            $permissions_names = array();
            foreach ($value["permissions"] as $perm) {
               foreach ($d["permissions"] as $r) {
                 if ($perm == $r["id"])
                   $permissions_names[] = $r["name"];
               }
            }
            return $permissions_names;
         }
      }
    } else { // return all role names
      $permissions_names = array();
      foreach ($d["permissions"] as $r) {
         $permissions_names[] = $r['name'];
      }
      return $permissions_names;
    }
    return;
  }

  // list all users, secure function, returns nothing if user is not logged in or
  // role is not admin
  function list_permissions_for_user( $user_in ) {
    global $_SESSION;

    // read the permissions database
    $d = loadDB();

    // check if the current user
    $user_name = $_SESSION["logged"];
    // is allowed to use permission
    $roles = array();
    foreach ( $d["users"] as $key => $value ) {
      if ($value["name"] == $user_name) {
         $roles = array_merge($roles, $value["roles"]);
      }
    }

    // for each role find the list of permissions
    $userpermissions = array();
    foreach ($d["roles"] as $key => $value) { // all known roles      
        foreach ($roles as $role) { // roles of the current user
           if ($value["id"] == $role) {
             $userpermissions = array_merge($userpermissions, $value["permissions"]);
           }
        }
    }
    
    // for each found permission find the name
    $userpermissions_str = array(); // array name instead of id
    foreach ($userpermissions as $perm) {
       foreach ($d["permissions"] as $key => $value) {
           if ($perm == $value["id"]) {
              $userpermissions_str[] = $value["name"];
           }
       }
    }
        
    return $userpermissions_str;
  }
?>
