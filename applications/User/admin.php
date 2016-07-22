<?php
  session_start(); /// initialize session
  include("../../php/AC.php");
  $user_name = check_logged(); /// function checks if visitor is logged.
  echo('<script type="text/javascript"> user_name = "'.$user_name.'"; </script>'."\n");

  $allowed = false;
  if (check_role( "admin" )) {
     echo('<script type="text/javascript"> role = "admin"; </script>'."\n");    
     $allowed = true;
  }

  $r = 'requests'; // collect .json files from the request directory to construct a table of current requests
  $req = array();
  if (is_dir($r) && is_readable($r)) {
    if ($handle = opendir($r)) {
      while (false !== ($entry = readdir($handle))) {
        $file_parts = pathinfo($entry);
        if ($entry != "." && $entry != ".." && $file_parts['extension'] == 'json') {
           $req[] = json_decode(file_get_contents( $r."/".$entry ), true );
        }
      }
      closedir($handle);
    }
  }

  ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Upload data portal.">
	<title>FIONA Admin Screen</title>

	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<link href="css/bootstrap.css" rel="stylesheet">
	<link href="css/bootstrap-responsive.min.css" rel="stylesheet">
	<link href="css/font-awesome.min.css" rel="stylesheet">
	<link href="css/bootswatch.css" rel="stylesheet">
        <link href="css/jquery-ui.css" rel="stylesheet" type="text/css"/>
        <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
        <!--[if lt IE 9]>
          <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <style type="text/css">
           .index h3 {
              text-align: left;
           }
           li { 
              display: inline;
           }
           .footer {
              height: 2em;
           }
           #user-role-processing span {
               width: 200px;
           }
           #roles-section-ui h3 {
              width: 80%;
           }
           #user-section-ui h3 {
              width: 80%;
           }
           .close {
              float: none;
           }
           .modal-header .close {
              float: right;
           }
           li h3 .close {
              float: right;
           }
           .permissions-row .close {
              float: right;
           }
           input[type="text"] {
              height: 30px;
           }
           input[type="email"] {
              height: 30px;
           }
           input[type="password"] {
              height: 30px;
           }
           .nav li {
              background-color: black;
           }
           li {
              background-color: #EEEEEE;
              border-radius: 1px 1px 1px 1px;
              display: inline-block;
              //margin: 5px;
              padding-left: 5px;
              width: 25%;
              min-width: 100px;
           }
           td {
	      vertical-align: top;
           }
           i {
              padding-right: 5px;
           }
        </style>
</head>
<body class="index" id="top" style="padding-top: 65px;">
    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="#">FIONA Admin Interface</a>
          <div class="nav-collapse collapse">
            <ul class="nav">
              <li class="active"><a href="/index.php">Home</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

  <div class="container">
    <div class="row">
      <div class="hero-unit">
        <h1>FIONA Admin Page</h2>
        <p class="lead">
          This page requires administrator privileges.
        </p>
      </div>
    </div>
    
    <?php if ( !$allowed ) { ?>
    <div class="row">
        The current user has no permissions to administer this portal.
    </div>
    <?php } else { ?>
        The current user has permissions to administer this portal.
        <hr>
        <h3>Users</h3>
        <div>
          <button class="btn btn-primary" type="button" data-toggle="modal" data-target="#add-new-user-dialog"><i class="icon-plus-sign"></i>Create new user</button>
        </div>
        <div id="user-section-ui"></div>
        <hr>
        <h3>Roles</h3>
        <div>
          <button class="btn btn-primary" type="button" data-toggle="modal" data-target="#add-new-role-dialog"><i class="icon-plus-sign"></i>Create new role</button>
        </div><br/>
        <div id="roles-section-ui"></div>
        <hr>
        <h3>Permissions</h3>
        <div>
          <button class="btn btn-primary"  type="button" data-toggle="modal" data-target="#add-new-permission-dialog"><i class="icon-plus-sign"></i>Create new permission</button>
        </div><br/>
        <div id="permissions-section-ui"></div>
        <hr>
    <div class="footer"></div>    
    <?php } ?>
    <script type="text/x-tmpl" id="user-section">
      <ul>
      {% for (var i = 0; i < o.length; i++) { %}
        <li class="user-row">
          <h3 class="btn btn-info" title="email: {%=o[i].email%}, lastKnownLogin: {%=o[i].lastTimeLoggedIn%}">{%=o[i].name%} <button class="close" onclick="removeUser('{%=o[i].name%}');">&times;</button><br/><small style="color: white;">{%=o[i].fullname%}, {%=o[i].organization%}, {%=o[i].legalok%}</small></h3>
          <div id="user-role-{%=o[i].name%}"></div>
          <button class="btn user-role-add" user="{%=o[i].name%}" data-toggle="modal" data-target="#add-role-to-user-dialog" onclick="jQuery('#add-role-to-user-user-name').attr('user', '{%=o[i].name%}');"><i class="icon-plus-sign"></i>Add Role</div>
        </li>
      {% } %}
      </ul>
    </script>
    <script type="text/x-tmpl" id="roles-section">
      <ul>
      {% for (var i = 0; i < o.length; i++) { %}
        <li class="roles-row">
          <h3 class="btn btn-info">{%=o[i]%}<button class="close" onclick="removeRole('{%=o[i]%}');">&times;</button></h3>
          <div id="permission-role-{%=o[i]%}"></div>  
          <button class="btn role-permission-add" user="{%=o[i]%}" data-toggle="modal" data-target="#add-permission-to-role-dialog" onclick="jQuery('#add-permission-to-role-role-name').attr('role', '{%=o[i]%}');"><i class="icon-plus-sign"></i>Add Permission</div>
        </li>
      {% } %}
      </ul>
    </script>    
    <script type="text/x-tmpl" id="permissions-section">
      <ul>
      {% for (var i = 0; i < o.length; i++) { %}
        <li class="permissions-row btn btn-info">{%=o[i]%}<button class="close" onclick="removePermission('{%=o[i]%}');">&times;</button></li>
      {% } %}
      </ul>
    </script>    
    <script type="text/x-tmpl" id="role-selection">
      <div id="role-selection-radio" class="btn-group" data-toggle="buttons-radio">
      {% for (var i = 0; i < o.length; i++) { %}
        <button type="button" class="btn role-selection-radio-entry" value="{%=o[i]%}">{%=o[i]%}</button>
      {% } %}
      </div>
    </script>    
    <script type="text/x-tmpl" id="permissions-selection">
      <div id="permission-selection-radio" class="btn-group" data-toggle="buttons-radio">
      {% for (var i = 0; i < o.length; i++) { %}
        <button type="button" class="btn permission-selection-radio-entry" value="{%=o[i]%}">{%=o[i]%}</button>
      {% } %}
      </div>
    </script>    
  </div>

  <div id="add-permission-to-role-dialog" class="modal hide fade" role="dialog" aria-hidden="true">
     <div class="modal-header">
         <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
         <h3>Add a permission to role</h3>
     </div>
     <div class="modal-body">
       <div id="permissions-selection-ui"></div>
       <input type="hidden" id="add-permission-to-role-role-name">
     </div>
     <div class="modal-footer">
       <a href="#" class="btn" data-dismiss="modal">Close</a>
       <a href="#" class="btn btn-primary" onclick="addPermissionToRole();" data-dismiss="modal">Save</a>
     </div>
  </div>
  <div id="add-role-to-user-dialog" class="modal hide fade" role="dialog" aria-hidden="true">
     <div class="modal-header">
         <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
         <h3>Add a role to user</h3>
     </div>
     <div class="modal-body">
       <div id="role-selection-ui"></div>
       <input type="hidden" id="add-role-to-user-user-name">
       <!-- <input id="add-role-name" type="text" placeholder="role name"> -->
     </div>
     <div class="modal-footer">
       <a href="#" class="btn" data-dismiss="modal">Close</a>
       <a href="#" class="btn btn-primary" onclick="addRoleToUser();" data-dismiss="modal">Save</a>
     </div>
  </div>
  <div id="add-new-user-dialog" class="modal hide fade" role="dialog" aria-hidden="true">
     <div class="modal-header">
         <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
         <h3>Add a new user</h3>
     </div>
     <div class="modal-body">
       <input id="add-user-name"     type="text"     placeholder="user name" autofocus><br/>
       <input id="add-user-full-name" type="text"    placeholder="full name"><br/>
       <input id="add-user-organisation" type="text" placeholder="organization"><br/>
       <input id="add-user-email"    type="email"    placeholder="email"><br/>
       <input id="add-user-password" type="password" placeholder="password">
     </div>
     <div class="modal-footer">
       <a href="#" class="btn" data-dismiss="modal">Close</a>
       <a href="#" class="btn btn-primary" onclick="addUser();" data-dismiss="modal">Create</a>
     </div>
  </div>
  <div id="add-new-role-dialog" class="modal hide fade" role="dialog" aria-hidden="true">
     <div class="modal-header">
         <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
         <h3>Add a new Role</h3>
     </div>
     <div class="modal-body">
       <input id="add-role-name" type="text" placeholder="role name" autofocus>
     </div>
     <div class="modal-footer">
       <a href="#" class="btn" data-dismiss="modal">Close</a>
       <a href="#" class="btn btn-primary" onclick="addRole();" data-dismiss="modal">Save</a>
     </div>
  </div>
  <div id="add-new-permission-dialog" class="modal hide fade" role="dialog" aria-hidden="true">
     <div class="modal-header">
         <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
         <h3>Add a new permission</h3>
     </div>
     <div class="modal-body">
       <input id="add-permission-name" type="text" placeholder="permission name" autofocus>
     </div>
     <div class="modal-footer">
       <a href="#" class="btn" data-dismiss="modal">Close</a>
       <a href="#" class="btn btn-primary" onclick="addPermission();" data-dismiss="modal">Save</a>
     </div>
  </div>

  <script src="/js/jquery-2.1.4.min.js"></script>
  <script src="js/jquery-ui.min.js"></script>
  <script type="text/javascript" src="js/tmpl.min.js"></script>
  <script type="text/javascript" src="/js/md5-min.js"></script>


  <script type="text/javascript">

    function addPermissionToRole() {
       var user = jQuery('#add-permission-to-role-role-name').attr('role');
       var name = jQuery(".permission-selection-radio-entry[class*='active']").val();
       if (typeof name == "undefined")
           return; // no role selected

       // remove all white spaces for now
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getRoles.php?action=addPermission&value='+name+'&value2='+user, function(data) {
          console.log('worked or not' + data);
          update();
          //jQuery('#add-permission-to-role-dialog').dialog('close');
       });
    }
    function addRoleToUser() {
       var user = jQuery('#add-role-to-user-user-name').attr('user');
       var name = jQuery(".role-selection-radio-entry[class*='active']").val();
       if (typeof name == "undefined")
           return; // no role selected

       // remove all white spaces for now
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getUser.php?action=addRole&value='+name+'&value2='+user, function(data) {
          console.log('worked or not' + data);
          update();
          //jQuery('#add-role-to-user-dialog').dialog('close');
       });
    }
    function addRole() {
       var name = jQuery('#add-role-name').val();
       // remove all white spaces for now
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getRoles.php?action=create&value='+name, function(data) {
          console.log('worked or not' + data);
          update();
          //jQuery('#add-new-role-dialog').dialog('close');
       });
    }
    function removeRoleFromUser( name, name2 ) {
         name = name.replace(/\ /g, "");
         jQuery.getJSON('/php/getUser.php?action=removeRole&value='+name+'&value2='+name2, function(data) {
            console.log('worked or not' + data);
            update();
         });         
    }    
    function removeRole( name ) {
         name = name.replace(/\ /g, "");
         jQuery.getJSON('/php/getRoles.php?action=remove&value='+name, function(data) {
            console.log('worked or not' + data);
            update();
         });
    }
    function addUser() {
       var name = jQuery('#add-user-name').val();
       if (name == "") {
          alert("Error: user name is empty");
          return;
       }
       if (name.indexOf(".") != -1) {
	 alert("Error: user name should not contain a dot");
	 return;
       }
       if (name.indexOf(" ") != -1) {
	 alert("Error: user name should not contain a space character");
	 return;
       }
       var email = jQuery('#add-user-email').val();
       if (email == "") {
          alert("Error: email field is empty");
          return;
       }
       var fullname = jQuery('#add-user-full-name').val();
       var organization = jQuery('#add-user-organisation').val();
       
       var password = jQuery('#add-user-password').val();
       hash = hex_md5(password);
       if (password == "") {
          alert("Error: password cannot be empty");
          return;
       }

       // remove all white spaces for now
       name  = name.replace(/\ /g, "");
       email = email.replace(/\ /g, "");
       // better use a post here to transmit data
       
       jQuery.post('/php/getUser.php?action=create&value='+name+'&value2='+email, { "pw": hash, "organization": organization, "fullname": fullname }, 
          function(data) {
             console.log('worked or not' + data);
             update();
             // jQuery('#add-new-user-dialog').dialog('close');
          }, "json");
       jQuery('#add-user-name').val('');
       jQuery('#add-user-email').val('');
       jQuery('#add-user-password').val('');
       jQuery('#add-user-organisation').val('');
       jQuery('#add-user-full-name').val('');
    }

    function removeUser( name ) {
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getUser.php?action=remove&value='+name, function(data) {
          console.log('worked or not' + data);
          update();
       });
    }
    function addPermission() {
       var name = jQuery('#add-permission-name').val();
       // remove all white spaces for now
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getPermissions.php?action=create&value='+name, function(data) {
          console.log('worked or not' + data);
          update();
          jQuery('#add-new-permission-dialog').dialog('close');
       });
    }

    function removePermission( name ) {
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getPermissions.php?action=remove&value='+name, function(data) {
          console.log('worked or not' + data);
          update();
       });
    }
    
    function removePermissionFromRole( role, name ) {
       name = name.replace(/\ /g, "");
       jQuery.getJSON('/php/getPermissions.php?action=remove&role='+role+'&value='+name, function(data) {
          console.log('worked or not' + data);
          update();
       });
    }

    // update everything
    function update() {
       // call list of documents
       jQuery.getJSON('/php/getUser.php', function(data) {
         dataAsArray = [];
         for (key in data) {
           dataAsArray.push(data[key]);
         }

         document.getElementById("user-section-ui").innerHTML = tmpl("user-section", dataAsArray);
         var callbacks = [];
         function createCallback(data, i) {
              return function(data2) {
                   var str = "Roles:<br>";
                   for (var j = 0; j < data2.length; j++) {
                      str += "<span>" + data2[j] + "<i class='close' onclick=\"removeRoleFromUser('" + data2[j] + "', '" + data[i]['name'] + "');\">&times;</i></span>";
                      if (j < data2.length-1)
                         str += ", ";
                   }
                   jQuery('#user-role-'+data[i]['name']).html(str);
              };         
         }
         
         for (var i = 0; i < dataAsArray.length; i++) {
           callbacks[i] = createCallback(dataAsArray, i);
         }         
         for (var i = 0; i < dataAsArray.length; i++) {
             jQuery.getJSON('/php/getRoles.php?user_name='+dataAsArray[i]['name'], callbacks[i]);
         }
       });
       jQuery.getJSON('/php/getRoles.php', function(data) {
         document.getElementById("roles-section-ui").innerHTML = tmpl("roles-section", data);
         document.getElementById("role-selection-ui").innerHTML = tmpl("role-selection", data);
         var callbacks2 = [];
         function createCallback2(data, i) {
              return function(data2) {
                   var str = "Permissions:<br>";
                   for (var j = 0; j < data2.length; j++) {
                      str += "<span>" + data2[j] + "<i class='close' onclick=\"removePermissionFromRole('" + data[i] + "', '" + data2[j] + "');\">&times;</i></span>";
                      if (j < data2.length-1)
                         str += ", ";
                   }
                   jQuery('#permission-role-'+data[i]).html(str);                                 
              };         
         }
         
         for (var i = 0; i < data.length; i++) {
           callbacks2[i] = createCallback2(data, i);
         }         
         for (var i = 0; i < data.length; i++) {
             jQuery.getJSON('/php/getPermissions.php?role='+data[i], callbacks2[i]);
         }
       });
       // add list of all permissions
       jQuery.getJSON('/php/getPermissions.php', function(data) {
         document.getElementById("permissions-section-ui").innerHTML = tmpl("permissions-section", data);
         document.getElementById("permissions-selection-ui").innerHTML = tmpl("permissions-selection", data);
       });
    }
    
    jQuery(document).ready(function() {
       update();        
       jQuery('#user-section-ui button .user-role-add').on('click', function() {
           // add the user name to the user-role-add-user-user-name
           var name = jQuery(this).attr('user');
           jQuery('#add-role-to-user-user-name').val(name);
       });
       jQuery('#permission-section-ui button .role-permission-add').on('click', function() {
           var name = jQuery(this).attr('role');
           jQuery('#add-permission-to-role-role-name').val(name);
       });
       //jQuery('#TABLE1').tableFilter();

       jQuery('.modal').on('shown', function() {
           jQuery(this).find("[autofocus]:first").focus();
       });       
    });
  </script>

  <script src="js/bootstrap.min.js"></script>
  <script src="js/bootswatch.js"></script>
 <!--  <script src="/js/bootstrap-modal.js"></script> -->

</body>
</html>
