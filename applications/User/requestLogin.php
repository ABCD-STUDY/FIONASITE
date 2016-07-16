<?php
session_start();
include("../../code/php/AC.php");

if (isset($_POST["username"])) {
   // store information about login request
   $username = $_POST["username"];

   if (isset($_POST["institution"]))
     $institution = $_POST["institution"];
   else
     echo "sending information failed...";

   if (isset($_POST["institutionwebsite"]))
     $institutionwebsite = $_POST["institutionwebsite"];
   else
     echo "sending information failed...";

   if (isset($_POST["email"]))
     $email = $_POST["email"];
   else
     echo "sending information failed...";

   if (isset($_POST["brief"]))
     $brief = $_POST["brief"];
   else
     echo "sending information failed...";

   if (isset($_POST["description"]))
     $description = $_POST["description"];
   else
     echo "sending information failed...";

   if (isset($_POST["role"]))
     $role = $_POST["role"];
   else
     echo "sending information failed...";

   if (isset($_POST["sponsor-name"]))
     $sponsor_name = $_POST["sponsor-name"];

   if (isset($_POST["sponsor-institution"]))
     $sponsor_institution = $_POST["sponsor-institution"];

   if (isset($_POST["sponsor-website"]))
     $sponsor_website = $_POST["sponsor-website"];

   if (isset($_POST["sponsor-email"]))
     $sponsor_email = $_POST["sponsor-email"];

   $now = date("F_j_Y_g:i_a");
   $message = array( "user name" => $username, "user email" => $email, "description" => $description, "date" => $now, "institution" => $institution, "institutionwebsite" => $institutionwebsite, "brief" => $brief, "role" => $role, "sponsor user name" => $sponsor_name, "sponsor institution" => $sponsor_institution, "sponsor email" => $sponsor_email, "sponsor website" => $sponsor_website );

   if (!is_dir("requests"))
      mkdir('requests');
   if (is_dir('requests')) {
      file_put_contents( "requests/".str_replace(" ", "", $username)."_".$now.".json",
                         json_encode( $message ) );
   }


   if (isset($_SERVER['SERVER_NAME']))
     $system = $_SERVER['SERVER_NAME'];
   else
     $system = "data portal";

   $sponsor = "";
   if (trim($role) == "Graduate Student" || 
       trim($role) == "Undergraduate Student" || 
       trim($role) == "Research Staff") {
      $sponsor = $sponsor."sponsor name: ".$sponsor_name."<br/>";
      $sponsor = $sponsor."sponsor institution: ".$sponsor_institution."<br/>";
      $sponsor = $sponsor."sponsor website: ".$sponsor_website."<br/>";
      $sponsor = $sponsor."sponsor email: ".$sponsor_email."<br/>";
   }

   $msg = '
   <html>
   <head>
      <title>Data use request</title>
   </head>
   <body>
      <p>The following data use request has been received:</p>
   '.'Date: '.$now.'<br/>
   '.'Name: '.$username.'<br/>
   '.'Role: '.$role.'<br/>
   '.$sponsor.'
   '.'Institution: '.$institution.'<br/>
   '.'Institution website: '.$institutionwebsite.'<br/>
   '.'Email: '.$email.'<br/>
   '.'[The user agrees to the terms and conditions printed below.]'.'<br/>
   '.'Brief description: <br/>'.$brief.'<br/><br/>
   '.'Project description: <br/>'.$description.'<br/><br/>
   '.'<br/><br/>
   '.file_get_contents('../../legal.html').
   '</body></html>'; 
   
   $headers  = 'MIME-Version: 1.0' . "\r\n";
   $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

   // Additional headers
   $headers .= 'To: Hauke Bartsch <hbartsch@ucsd.edu>' . "\r\n";
   $headers .= 'From: '.$system.' <mmiladmin@ucsd.edu>' . "\r\n";
   $headers .= 'Cc: '.$email. "\r\n";
   //$headers .= 'Bcc: birthdaycheck@example.com' . "\r\n";

   // Mail it
   mail('hbartsch@ucsd.edu', $system.': Data use request for '.$username, $msg, $headers);

   echo("<p>An email has been sent to your account with a copy of the (digitally) signed use agreement. Please keep a copy of this document for further reference.</p><p>Thank you for requesting an account on this system. The system administrator will contact you shortly.</p>");
   return;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Login to Data Portal">
	<title>Login to Data Portal</title>

	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

<!--        <link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/start/jquery-ui.css" rel="stylesheet" type="text/css"/> -->
	<link href="css/bootstrap.css" rel="stylesheet">
	<link href="css/bootstrap-responsive.min.css" rel="stylesheet">
	<link href="css/font-awesome.min.css" rel="stylesheet">
	<link href="css/bootswatch.css" rel="stylesheet">
        <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
        <!--[if lt IE 9]>
          <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

</head>
<body class="index" id="top">
  <noscript>
    <div id="js-warning" class="warning">
        To be able to access all the features of this website, you need a browser that supports JavaScript, and it needs to be enabled.
    </div>
  </noscript>

  <div class="container">
   <div class="hero-unit">
       <h1>Data Portal</h2>
       <p class="lead">
         Request access by providing your information and agreeing to our data use and data sharing policy.
       </p>
   </div>
   <div class="row">
    <div class="span12">
      <div id="legal-text" style="height: 250px; overflow-y:scroll; border: 1px solid gray; padding: 15px;"><a href="/legal.html">Link to legal text.</a></div>
      <label class="checkbox" style="margin-top: 5px;">
         <input id="agree-to-agreement" type="checkbox" required>I have read and accept the Terms of Use<sup>&nbsp;*</sup>
      </label>
      <hr/>
      <form action="requestLogin.php" method="post" id="request-login-form" data-validate="parsley" novalidate="novalidate">
        Please specify your research role<sup>*</sup>:<br>
        <p>
	   <input type="hidden" id="role" name="role" value="" />
	   <div class="btn-group researcher-role" data-toggle="buttons">
	     <div class="row">
	       <label class="span3" style="text-align: left;">
  	         <input type="radio" name="researcher-role" style="vertical-align: top;">
	         Faculty Researcher
	       </label>
	       <label class="span3" style="text-align: left;">
	         <input type="radio" name="researcher-role" style="vertical-align: top;">
	         Research Scientist
	       </label>
	       <label class="span3" style="text-align: left;">
 	         <input type="radio" name="researcher-role" style="vertical-align: top;">
	         Postdoctoral Researcher
	       </label>
	     </div>
	     <div class="row">
	       <label class="span3" style="text-align: left;">
	         <input type="radio" name="researcher-role" style="vertical-align: top;">
	         Graduate Student
	       </label>
	       <label class="span3" style="text-align: left;">
	         <input type="radio" name="researcher-role" style="vertical-align: top;">
	         Undergraduate Student
	       </label>
	       <label class="span3" style="text-align: left;">
	         <input type="radio" name="researcher-role" style="vertical-align: top;">
	         Research Staff
	       </label>
             </div>
	   </div>
        </p>
        <div id="sponsor-information" style="display: none;">
	       <small>Our institutional review board (IRB) requires that a Faculty Researcher or Research Scientist sponsor your Data Use application. Please provide their contact information.</small><br/>
	       <input type="text" name="sponsor-name" placeholder="sponsor name" title="The full name of your sponsor" id="sponsor-username-field" data-required="true" data-trigger="change" data-notblank="true" /><sup>&nbsp;*</sup>
	       <input type="text" name="sponsor-institution" placeholder="sponsor institution" title="The full name of your sponsor's institution" id="sponsor-institution-field" data-required="true" data-trigger="change" data-notblank="true"/><sup>&nbsp;*</sup>
	       <input type="text" name="sponsor-website" placeholder="sponsor's institution website" title="The full name of your sponsor's institution website." id="sponsor-website-field" data-required="true" data-trigger="change" data-notblank="true"/><sup>&nbsp;*</sup>
	       <br/>
	       <input type="text" name="sponsor-email" placeholder="sponsor@institutional.email" title="The email address of your sponsor" id="sponsor-email-field" data-required="true" data-trigger="change" data-notblank="true"/><sup>&nbsp;*</sup>
	       <input type="text" name="sponsor-email2" placeholder="retype sponsor email" title="The email of your sponsor" id="sponsor-email2-field" data-required="true" data-trigger="change" data-equalto="#sponsor-email-field" data-notblank="true"/><sup>&nbsp;*</sup>

        </div>

        If none of the above describe you, please send an email to chd-mailer@ucsd.edu for further assistance.
	<hr>
           <input type="text" name="username" placeholder="my full name" title="Your full name" id="username-field" required data-required="true" autofocus data-trigger="change"/><sup style="vertical-align: middle;">&nbsp;*</sup>&nbsp;&nbsp;
           <input type="text" name="institution" title="Your institution's name. We use it to verify your identity." placeholder="institution's name" id="institution-field" required data-trigger="change"/><sup style="vertical-align: middle;">&nbsp;*</sup>&nbsp;&nbsp;
           <input type="url"  name="institutionwebsite" placeholder="my institution's website" title="Your institution's website. We use it to verify your identify." id="institution-website-field" data-trigger="change"/><sup>&nbsp;*</sup>
           <br/>
           <input type="email" name="email" placeholder="my@institutional.email" title="Your institution's email address." id="email-field" style="vertical-align: top;" required data-trigger="change"><sup>&nbsp;*</sup>&nbsp;&nbsp;
           <input type="email" name="email" placeholder="retype email" id="email-field-2" title="Re-type your email to prevent typos." style="vertical-align: top;" required data-trigger="change" data-equalto="#email-field"/><sup>&nbsp;*</sup> 
           <br/>
         <input class="span12" name="brief" id="brief" style="margin-bottom: 10px; padding-left: 5px;" required placeholder="brief informative title of planned data use *" title="brief informative title of planned data use" data-notblank="true" /><br/>
         <textarea class="span12" rows="4" cols="auto" name="description" id="description" required placeholder="Describe briefly your planned use of the data and the major question(s) you hope to address. We will review your submission and create an account. We will contact you via email. *" data-notblank="true"></textarea><br/>
         <small style="margin-bottom: 5px;"><sup>*</sup> Required</small>
         <br/>
         <input type="submit" class="btn btn-primary" value="Submit using electronic signature" id="submit-now" title="Fill out all the fields above to make this button work."/></br>
      </form>
      <br/>
    </div>
   </div>
  </div>
  
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
  <script src="/js/parsley.min.js"></script>
  <!-- create an md5sum version of the password before sending it -->
  <script src="/js/md5-min.js"></script>

  <script type="text/javascript">
     if(typeof String.prototype.trim !== 'function') {
       String.prototype.trim = function() {
         return this.replace(/^\s+|\s+$/g, ''); 
       }     
     }

     function checkEverything() {
       var one = (jQuery('#agree-to-agreement').attr('checked') == "checked");
       var two = true; // (Does not work in IE9) !jQuery('#email-field').is(':invalid');
       var three = (jQuery('#email-field').val() == jQuery('#email-field-2').val());
       var four = (jQuery('#description').val() != "");
       var five = (jQuery('#username-field').val() != "");
       var six = (jQuery('#institution-field').val() != "");
       var seven = (jQuery('#role').val() != "");
       var eight = false;

       var t = jQuery('#role').val().trim();
       if (t == "Graduate Student" || t == "Undergraduate Student" || t == "Research Staff") {
          var t1 =  (jQuery('#sponsor-email-field').val() == jQuery('#sponsor-email2-field').val());
	  var t2 = (jQuery('#sponsor-username-field').val() != "");
	  var t3 = (jQuery('#sponsor-institution-field').val() != "");
	  var t4 = (jQuery('#sponsor-website-field').val() != "");
	  if (t1 && t2 && t3 && t4)
	    eight = true;
       } else {
          eight = true;
       }

       if (one && two && three && four && five && six && seven && eight) {
         jQuery('#submit-now').removeAttr('disabled');
       } else {
         jQuery('#submit-now').attr('disabled', 0);
       }
     }

     jQuery(document).ready(function() {
       // if we have javascript set the submit button to disabled
       jQuery('#submit-now').attr('disabled','disabled');
       jQuery('#sponsor-information').hide();

       jQuery('#legal-text').load('/legal.html');
       //jQuery('.btn').button();

       // make the hidden input field take on the selected role
       jQuery('.researcher-role label').click(function() {
         jQuery('#role').val(jQuery(this).text());
	 var t = jQuery(this).text().trim();
	 if (t == "Graduate Student" ||
  	     t == "Undergraduate Student" ||
	     t == "Research Staff") {
	     jQuery('#sponsor-information').fadeIn();
         } else {
	     jQuery('#sponsor-information').fadeOut();
         }

         checkEverything();
       });

       jQuery( '#request-login-form' ).parsley( 'addListener', {
          onFieldValidate: function ( elem ) {

            // if field is not visible, do not apply Parsley validation!
            if ( !$( elem ).is( ':visible' ) ) {
               return true;
            }

            return false;
          }
       });
     });
     jQuery('#agree-to-agreement').change(function() {
       checkEverything();
     });
     jQuery('#email-field').change(function() {
       checkEverything();
     });
     jQuery('#institution-field').change(function() {
       checkEverything();
     });
     jQuery('#email-field-2').change(function() {
       checkEverything();
       var three = (jQuery('#email-field').val() == jQuery('#email-field-2').val());
       if (!three) {
         jQuery('#email-field-2').attr('invalid', 0);
       } else {
         jQuery('#email-field-2').removeAttr('invalid');
       }
     });
     jQuery('#description').bind('keyup change', function() {
       checkEverything();
     });
  </script>

</body>
</html>
