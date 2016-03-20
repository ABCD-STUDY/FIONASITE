<?php

function getSystemMemInfo() 
{       
    $data = explode("\n", file_get_contents("/proc/meminfo"));
    $meminfo = array();
    foreach ($data as $line) {
            // some lines will not contain a colon, for those add one at the end
    	    list($key, $val) = explode(":", "${line}:");
    	    $meminfo[$key] = trim($val);
    }
    return $meminfo;
}


  // collect some stats and return them as a json object
  $data = array();
  $d = '/data/site/archive';
  $data['disk_free_percent'] = round(100.0 * disk_free_space($d) / disk_total_space($d), 2);
  $meminfo = getSystemMemInfo();
  // round(100.0 * intval($meminfo['MemFree']) / intval($meminfo['MemInfo']), 2);
  $data['memory_free_percent'] = round(100.0 * floatval(split(' ',$meminfo['MemFree'])[0]) / floatval(split(' ',$meminfo['MemTotal'])[0]), 2);
  $data['load_avg'] = sys_getloadavg()[0];
  $data['hostname'] = gethostname();

  echo(json_encode($data, JSON_PRETTY_PRINT));

?>