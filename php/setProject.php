<?php
   session_start();

   include("AC.php");
   $user_name = check_logged();
   
   if (isset($_GET['project_name'])) {
       setUserVariable($user_name, "project_name", $_GET['project_name']);
       echo ("{ \"message\": \"done\" }");
       return;
   }
   echo ("{ \"message\": \"Error: no project_name argument\" }");
?>