<?php
session_start();
include("../../code/php/AC.php");

if (isset($_POST["email"])) {
   // Check if this user exists
   $email = $_POST["email"];
   $name = getUserNameFromEmail($email);
   //echo "Found this name: ".$name." in our records";
   if ($name == "unknown") {
      audit( "newPassword", "account does not exist ".$_POST["email"] );
      echo "<script>message = \"This account does not exist.\";</script>";
   } else {
      // create a new password for this account and send to user
      audit( "newPassword", "send to ".$_POST["email"] );
      $pw = substr(uniqid(), 0, 8);
      $md5version = md5($pw);
      changePassword( $name, $md5version );
      // email user
      $serv = split(':', $_SERVER['HTTP_HOST']);
      $message = "A new password has been created for account \"".$name."\" on ".$serv[0].". Login using this password: \"".$pw."\"";
      $headers = "From: admin@dataportal.edu\r\n";
      mail($email, 'Password Reset', $message, $headers);
      echo "<script>message = \"An email has been send to your account. After you receive the email, try to login again <a href='/index.php'>here</a>.\";</script>";
   }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Request new password to be send to email">
	<title>Request new password</title>

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
       <h1>Data Portal</h2>
       <p class="lead">
         Request a new password to be send to your email address.
       </p>
     </div>
   </div>
   <div class="row">
    <div class="span4"> </div>
    <div class="span3">
      <form action="newPassword.php" method="post" id="request-password">
         <input type="email" name="email" placeholder="email" class="span3" autofocus/>
      </form>
    </div>
   </div>
   <div class="row">
    <span id="message"></span>
   </div>
  </div>
  
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
  <!-- create an md5sum version of the password before sending it -->
  <script src="/js/md5-min.js"></script>

  <script type="text/javascript">
     jQuery(document).ready(function() {
        jQuery('#message').html(message);
     });
  </script>

</body>
</html>
