<?php
 session_start();

 global $_SESSION;

 if (isset($_SESSION["logged"])) {
    unset($_SESSION["logged"]);
    echo("success");
    return;
 } else {
    echo("session variable does not exist");
    return;
 }

?>