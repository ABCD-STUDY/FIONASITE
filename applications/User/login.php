<?php
session_start();
include("../../php/AC.php");

if (isset($_POST["ac"]) && $_POST["ac"]=="log") { /// do after login form is submitted
     if ($USERS[$_POST["username"]]==$_POST["pw"]) { /// check if submitted username and password exist in $USERS array
     	  // as a features users can login using their email address
	  // here we need to get the real user name and use that instead of the email address
	  if (strpos($_POST["username"], "@") !== false) {
	    // found email as user name, what is the real name?
	    $_SESSION["logged"]=getUserNameFromEmail($_POST["username"]);
          } else {
            $_SESSION["logged"]=$_POST["username"];
          }
     } else {
          audit( "login", "incorrect password name for ".$_POST["username"] );
          echo 'Incorrect username/password. Please, try again.';
     };
};
if (array_key_exists($_SESSION["logged"],$USERS)) {
     $l = strlen('/login.php');
     if (isset($_POST["url"]) && $l > 0 && substr($_POST["url"],-$l) === '/login.php')
        $u = $_POST["url"];
     else
        $u = "/index.php";
     header("Location: ".$u); // if user is logged go to front page
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Login to Site FIONA">
	<title>Login to Data Portal</title>

	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<link href="css/bootstrap.css" rel="stylesheet">
	<link href="css/bootstrap-responsive.min.css" rel="stylesheet">
	<link href="css/font-awesome.min.css" rel="stylesheet">
	<link href="css/bootswatch.css" rel="stylesheet">
        <link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/start/jquery-ui.css" rel="stylesheet" type="text/css"/>
        <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
        <!--[if lt IE 9]>
          <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

</head>
<body class="index" id="top">
  <div class="container">
   <div class="row">
     <div class="hero-unit">
       <h1>FIONA Login</h2>
       <p class="lead">
          <center>
	     A service provided by the Data Analysis and Informatics Core of UC San Diego
	  </center>
       </p>
     </div>
   </div>
   <div class="row">
    <div class="span4"> </div>
    <div class="span3">
      <form action="login.php" method="post" id="login-form">
         <input type="hidden" name="ac" value="log">
         <input type="hidden" name="pw" id="pw">
         <input type="hidden" name="url" id="url" value="<?php echo $_GET['url']; ?>">
         <input type="text" name="username" placeholder="user" class="span3" autofocus/>
      </form>
      <input id="pw-field" type="password" name="password" placeholder="********" onkeypress="handleKeyPress(event, this.form)" class="span3"><br/>
      <div align="right">
         <input type="submit" class="btn" value="Login" form="login-form" class="span3"/><br>
         <!-- <a href="/applications/User/requestLogin.php" class="small">request access</a> /<a href="/applications/User/newPassword.php" class="small">send new password</a> -->
      </div>
    </div>
   </div>
  </div>
  
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
  <!-- create an md5sum version of the password before sending it -->
  <script src="/js/md5-min.js"></script>

  <script type="text/javascript">
     jQuery(document).ready(function() {
        // prevent enter in the user field to submit form
        jQuery('input').keydown(function(event) {
           if (event.keyCode == 13) {
      	     if (jQuery(this).attr('name') == "username") {
               event.preventDefault();
	       jQuery('#pw-field').focus();
      	       return false;
             }
      	   }
        });
     
        // calculate and copy hash value after entering password
        jQuery('#pw-field').blur(function() {
	   rewritePW();
        });
     });

     function rewritePW() {
           hash = hex_md5(jQuery('#pw-field').val());
           jQuery('#pw').val(hash);     
     }

     function handleKeyPress(e, form) {
       var key = e.keyCode || e.which;
       if (key == 13) {
          rewritePW();
          jQuery('#login-form').submit();
       }
     }

  </script>

</body>
</html>
