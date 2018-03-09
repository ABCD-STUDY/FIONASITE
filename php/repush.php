<?php
#
# Allow the users to repush data (deletes information in /data/site/raw and resends data from /data/site/archive/).
#
#  requires:
#     yum install php-posix
#  add cron job for processing user
#    */1 * * * * /usr/bin/php /var/www/html/php/repush.php >> /var/www/html/server/logs/repush.log 2>&1
#

function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (is_dir($dir."/".$object))
           rrmdir($dir."/".$object);
         else
           unlink($dir."/".$object); 
       } 
     }
     rmdir($dir); 
   } 
}

// This will produce error messages if the script runs from the cron-job. This is normal.
session_start(); /// initialize session
include("AC.php");
$user_name = check_logged(); /// function checks if visitor is logged.
if (!$user_name) {
    if (!file_exists('/var/www/html/php/repush.jobs')) {
       exit();
    }

    // if we are not logged in we can still repush something that is queued
    // echo("user name:".json_encode(posix_getpwuid(posix_geteuid()))."\n"); 
    if ( posix_getpwuid(posix_geteuid())['name'] == "processing" ) {  // this only works if php-posix is installed on this system
        // we should only try to send if we are not yet running
        $lock_file = fopen('/var/www/html/server/.pids/repush.pid', 'c');
        $got_lock = flock($lock_file, LOCK_EX | LOCK_NB, $wouldblock);
        if ($lock_file === false || (!$got_lock && !$wouldblock)) {
            throw new Exception(
                "Unexpected error opening or locking lock file. Perhaps you " .
                "don't  have permission to write to the lock file or its " .
                "containing directory?"
            );
        } else if (!$got_lock && $wouldblock) {
            exit("Another instance is already running; terminating.\n");
        }
        
        // Lock acquired; let's write our PID to the lock file for the convenience
        // of humans who may wish to terminate the script.
        ftruncate($lock_file, 0);
        fwrite($lock_file, getmypid() . "\n");
        
        $content = explode("\n", file_get_contents('/var/www/html/php/repush.jobs'));
        if (count($content) > 0) {
	    $firstjob = explode(" ",$content[0]);
	    $project = $firstjob[1];
	    if ($project == "ABCD") {
                $project = "";
            }

            // only run the first job
            $studyinstanceuid = $firstjob[0];
            if (strlen($studyinstanceuid) == 0) {
                return;
            }   
            echo(date(DATE_ATOM)." found repush job for: \"".$content[0]."\"\n");

            $path = "/data".$project."/site/raw/".$studyinstanceuid;
            $p = realpath($path);
            if (strpos($p, "/data".$project."/site/raw/") !== 0) {
                echo("Error: asked to remove directory that is not in /data".$project."/site/raw/ ->\"".$p."\" of ".$path."\n");
                // lets remove this entry
                array_shift($content);
                file_put_contents('/var/www/html/php/repush.jobs', implode("\n",$content));
                // still send the files again
                $path = "/data".$project."/site/archive/scp_".$studyinstanceuid;
                if (file_exists($path)) {
                    exec('/var/www/html/server/utils/s2m.sh '.$path.' '.$project.' &',$output); // execute and return immediately 
                    echo("s2m result: ".implode("\n",$output));
                } else {
                    echo("Error: could not find directory: ".$path."\n");
                }
                return; // don't do anything if we are asked for a different directory, only raw is supposed to work
            }
            if (file_exists($p)) {
                // remove this job from the queue first
                array_shift($content);
                file_put_contents('/var/www/html/php/repush.jobs', implode("\n",$content));
                
                // now try to delete all the existing files and symbolic links in this directory
                echo("remove all files in:".$p."\n");
                rrmdir($p);
                $path = "/data".$project."/site/archive/scp_".$studyinstanceuid;
                echo(date(DATE_ATOM)." try to re-run: ".$path."\n");
                exec('/var/www/html/server/utils/s2m.sh '.$path.' '.$project.' &',$output); // execute and return immediately 
                echo("s2m result: ".implode("\n",$output)."\n");
            }
        }
        ftruncate($lock_file, 0);
        flock($lock_file, LOCK_UN);
    }
    return; // nothing
}

// only the web-call will use the project as a post argument
$project = "";
if (isset($_POST['project'])) {
    $project = $_POST['project'];
}

// remove the data from /data/site/raw
$studyinstanceuid = "";
if (isset($_POST['studyinstanceuid'])) {
    $studyinstanceuid = $_POST['studyinstanceuid'];
}
//syslog(LOG_EMERG, "study instance uid: ".$studyinstanceuid);
if ($studyinstanceuid == "") {
    return;
}
// place a job into repush.jobs
file_put_contents('/var/www/html/php/repush.jobs', $studyinstanceuid." ".$project."\n", FILE_APPEND);

?>
