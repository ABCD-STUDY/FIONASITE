<?php

$config = json_decode(file_get_contents('config.json'), TRUE);
$proxy = "";
$proxyport = 3128;
if (isset($config['WEBPROXY'])) {
  $proxy=$config['WEBPROXY'];
  $proxyport=$config['WEBPROXYPORT'];
}

// get the token from the config file
if (! file_exists("config.json")) {
   echo ("{ \"message\": \"Error: could not read the config file\", \"ok\": \"0\" }");
   return;
}
$configs = json_decode(file_get_contents("config.json"), TRUE);
if (!isset($configs['CONNECTION'])) {
   echo ("{ \"message\": \"Error: could not find CONNECTION setting in the config file\", \"ok\": \"0\" }");
   return;
}
$token = $configs['CONNECTION'];
if ($token == "") {
   echo ("{ \"message\": \"Error: no token found in config file\", \"ok\": \"0\" }");
   return;
}
//echo($token);

  $data = array(
      'token' => $token,
      'content' => 'event',
      'format' => 'json',
      'returnFormat' => 'json'
  );
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://abcd-rc.ucsd.edu/redcap/api/');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  if ($proxy != "") {
     curl_setopt($ch, CURLOPT_PROXY, $proxy);
     curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
     curl_setopt($ch, CURLOPT_PROXYPORT, $proxyport);
  }
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
  //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, 400);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
  $output = curl_exec($ch);
  print $output;
  print curl_error($ch);
  curl_close($ch);

?>