<?php

$action = "";
$study = "";
$project = "";

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

if (isset($_GET['project'])) {
    $project = $_GET['project'];
} else {
    echo ("{ \"ok\": 0, \"message\": \"project not set\" }");
    return;
}

if ($project == "ABCD") {
   // default project setting
   $project = "";
}

if ( $action == "getStudy" ) {

    // run compliance check here
    //echo ("study: ".$study);
    file_put_contents('/tmp/'.$study, "study: ".$study." please run compliance check now");
    chmod ('/tmp/'.$study, 0777);
    $cpath = 'request_compliance_check';
    if(!is_dir($cpath)) {
       mkdir($cpath, 0777);
    }
    rename('/tmp/'.$study, $cpath.DIRECTORY_SEPARATOR.$study);    

    //$d = 'output/scp_'.$study.'/series_compliance/compliance_output.json';
    $d = '/data'.$project.'/site/output/scp_'.$study.'/series_compliance/compliance_output.json';
    if (!file_exists($d)) {
        echo ("{ \"ok\": 0, \"message\": \"file could not be found\" }");
        return;
    }
    $comp = json_decode(file_get_contents($d), true);
    echo(json_encode($comp, JSON_PRETTY_PRINT));
    return;
} else {
    // or just return everything

    // data is located in /data/site/output/scp_<study instance uid>/
    //$d = 'output';
    $d = '/data'.$project.'/site/output';
    
    $studydirs = glob($d."/scp_*");
    
    $withtime = array();
    foreach ($studydirs as $s) {
        if ( in_array($study, array( ".", "..")))
            continue;
        $withtime[$s] = filemtime($s);
    }
    
    asort($withtime);
    
    $f = array();
    foreach($withtime as $key => $value) {
        $comp = json_decode(file_get_contents($key."/series_compliance/compliance_output.json"), true);
        if (isset($comp)) {
            $f[] = $comp;
        }
    }
}

?>
