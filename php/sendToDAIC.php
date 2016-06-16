<?php

$action = "";
$study = "";

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"action not set\" }");
    return;
}

if (isset($_GET['study'])) {
    $study = $_GET['study'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"study not set\" }");
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
            // move the file from quarantine to outbox
            // TODO: Not yet, test first
            echo("MOVE: /data/quarantine/" . $file . " TO: /data/outbox/" . $newf . "\n");
            //rename("/data/quarantine/" . $file,"/data/outbox/" . $newf);
        }
    }
    /*Close the handle*/
    closedir($handle);
    if ($found) {
        echo ("{ \"ok\": 1, \"message\": \"study moved to outbox\" }");
        return;
    } else {
        echo ("{ \"ok\": 0, \"message\": \"study not found in quarantine\" }");
        return;
    }
}

// Reference:
// Bulk Rename Files in a Folder - PHP
// http://stackoverflow.com/questions/4993590/bulk-rename-files-in-a-folder-php

?>
