<?php

$action = "";
$study = "";

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"ERROR: action not set\" }");
    return;
}

if (isset($_GET['study'])) {
    $study = $_GET['study'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"ERROR: study not set\" }");
    return;
}

if ($handle = opendir('/data/quarantine')) {
    $found = false;
    // iterate the files in the directory
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            if(!strstr($file, $study)) {
                continue; // skip the files if it does not contain $study
            }
            $found = true;
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 1, \"message\": \"Ready to send\" }");
        return;
    }
}

if ($handle = opendir('/data/outbox')) {
    $found = false;
    // iterate the files in the directory
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            if(!strstr($file, $study)) {
                continue; // skip the files if it does not contain $study
            }
            $found = true;
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 2, \"message\": \"Pending send to DAIC\" }");
        return;
    }
}

if ($handle = opendir('/data/DAIC')) {
    $found = false;
    // iterate the files in the directory
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            if(!strstr($file, $study)) {
                continue; // skip the files if it does not contain $study
            }
            $found = true;
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 3, \"message\": \"Sent to DAIC\" }");
        return;
    } else {
        echo ("{ \"ok\": 0, \"message\": \"ERROR: study not found\" }");
        return;
    }
}

?>
