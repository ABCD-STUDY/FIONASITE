<?php

function getSystemMemInfo() 
{       
    $data = explode("\n", file_get_contents("/proc/meminfo"));
    $meminfo = array();
    foreach ($data as $line) {
    	    list($key, $val) = explode(":", $line);
    	    $meminfo[$key] = trim($val);
    }
    return $meminfo;
}


  // collect some stats and return them as a json object
  $data = array();
  $d = '/data/site/archive';
  $data['disk_free_percent'] = round(100.0 * disk_free_space($d) / disk_total_space($d), 2);
  $data['load_avg'] = sys_getloadavg()[0];
  $data['hostname'] = gethostname();

  echo(json_encode($data, JSON_PRETTY_PRINT));

?>