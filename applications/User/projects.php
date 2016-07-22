<?php
session_start();
include("../../code/php/AC.php");
// will log the user out if he is not logged in
$user_name = check_logged();

  $r = 'requests'; // collect .json files from the request directory to construct a table of current requests
  $req = array();
  if (is_dir($r) && is_readable($r)) {
    if ($handle = opendir($r)) {
      while (false !== ($entry = readdir($handle))) {
      $file_parts = pathinfo($entry);
        if ($entry != "." && $entry != ".." && $file_parts['extension'] == 'json') {
           $req[] = json_decode(file_get_contents( $r."/".$entry ), true );
        }
      }
      closedir($handle);
    }
  }

  if (isset($_SESSION['project_name']))
     $project_name = $_SESSION['project_name'];
  else {
     // take the first project
     $projs = json_decode(file_get_contents('/code/php/getProjectInfo.php'),TRUE);
     if ($projs)
        $project_name = $projs[0]['name'];
     else
        $project_name = "Project01";
  }

  echo('<script type="text/javascript"> user_name = "'.$user_name.'"; project_name = "'.$project_name.'"; </script>');

  // cache results of the mapquest to speed things along
  $CACHE = array();
  function getLocation( $value2 ) {
    global $CACHE;
    if (empty($CACHE)) {
       // try to read from disk
       $CACHE = json_decode(file_get_contents("locationcache.json"), true);
    }
    if (array_key_exists( $value2, $CACHE ) ) {
      $ret = $CACHE[$value2];
    } else {
      $ret = json_decode(file_get_contents("http://open.mapquestapi.com/nominatim/v1/search.php?format=json&q=".urlencode($value2)), true);
      // and add to cache
      $CACHE[$value2] = $ret;
      // and save the cache again
      file_put_contents("locationcache.json", json_encode($CACHE));
    }
    return $ret;
  }

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Projects">
	<title>Projects on this portal</title>

	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<link href="/css/bootstrap.css" rel="stylesheet">
	<link href="css/bootstrap-responsive.min.css" rel="stylesheet">
	<link href="css/font-awesome.min.css" rel="stylesheet">
	<link href="css/bootswatch.css" rel="stylesheet">
        <link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/start/jquery-ui.css" rel="stylesheet" type="text/css"/>
        <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
        <!--[if lt IE 9]>
          <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <style>

          td:first-child { width: 63%; padding-left: 10px; }
          td:last-child { text-align: right; padding-right: 10px; }
          td { vertical-align: top; }
          h4 { font-size: 12pt; font-weight: 400; }
          th { font-size: 12pt; font-weight: 400; }
          td { font-size: 12pt; font-weight: 300; }
        </style>

</head>
<body class="index" id="top">

    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="#">Data Portal Users <span class="current-project"></span></a>
          <div class="nav-collapse collapse">
            <ul class="nav">
              <li class="active"><a href="/index.php">Home</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

  <div class="container" style="margin-top: 30px;">

   <div class="row">
     <!-- add a google map -->
     <div id="map-container" style="position: relative; background-color: rgb(229, 227, 223); overflow: hidden; webkit-transform: translateZ(0); display: block; width: 100%; height: 500px;">
         <div id="map-canvas" style="position: absolute; left: 0px; top: 0px; overflow: hidden; width: 100%; height: 100%; z-index: 0"></div></div>
	 <div style="text-align: right;"><small>[location data for institutions is provided by http://open.mapquestapi.com]</small></div>
    </div>
    <div class="row">&nbsp;</div> 
    <div class="row">
      <table style="width: 100%;">
       <thead>
         <tr>
           <th style="border-bottom: 2px solid #6678b1; text-align: left; padding-left: 10px;">
             Description
           </th>
           <th style="border-bottom: 2px solid #6678b1; text-align: right; margin-left: 10px;">
             Researcher
           </th>
           <th style="border-bottom: 2px solid #6678b1; text-align: right; padding-right: 10px;">
             Organization
           </th>
         </tr>
       </thead>
       <tbody>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
  <script src="//maps.googleapis.com/maps/api/js?sensor=false&libraries=places,geometry,visualization&v=3.exp&key=AIzaSyDyQ724f8tTmdRYiVncVyMiJCYDp-Tc6YU"></script>

<?php
              $count = 0;
              $locations = array();
	      $mappoints = array();
              foreach ($req as $key => $value) {
                if ($count % 2 == 0) {
		  echo "<tr style=\"background-color: #EEEEEE;\">";
                } else {
                  echo "<tr style=\"background-color: #FFFFFF;\">";
                }
                $notFound = 0;
                foreach ($value as $key2 => $value2) {
                  if ( $key2 == "user email" ) {
                    // now check if that user has an account echo "<td>".$value2."</td>";
		    if (getUserNameFromEmail( $value2 ) == "unknown")
		       $notFound = 1;
                  }
                }
                if ($notFound == 1) { // skip this user
                   continue;
                }
                $count = $count + 1;

		$brief = "";
                foreach ($value as $key2 => $value2) {
                  if ( $key2 == "brief" )
                    $brief = $value2;
                }
                $date = "";
                foreach ($value as $key2 => $value2) {
                  if ( $key2 == "date" )
                    $date = $value2;
                }
 
                foreach ($value as $key2 => $value2) {
                  if ( $key2 == "description" )
                    echo "<td><h4>".$brief."</h4><div style='margin-top: -10px; margin-bottom: 10px;'><small>".implode(' ', array_splice(split('_', $date),0,3))."</small></div><p>".$value2."</p></td>";
                }
		$user_name = "";
                foreach ($value as $key2 => $value2) {
                  if ( $key2 == "user name" ) {
                    echo "<td style='padding-top: 10pt;text-align: right;'>".$value2."</td>";
                    $user_name = $value2;
                  }
                }
                foreach ($value as $key2 => $value2) {
                  if ( $key2 == "institution" ) {
		    // where is this?
                    // $ret = json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($value2)),true);
                 
		    //$ret = json_decode(file_get_contents("http://open.mapquestapi.com/nominatim/v1/search.php?format=json&q=".urlencode($value2)), true);
		    $ret = getLocation( $value2 );
                    $locations[] = array( "name" => $value2,
		                          "user_name" => $user_name,
		                          //"lat" => $ret['results'][0]['geometry']['location']['lat'], 
					  //"lng" => $ret['results'][0]['geometry']['location']['lng'],
					  "lat" => $ret[0]['lat'],
					  "lng" => $ret[0]['lon'],
                                          "brief" => $brief );
		    if (array_key_exists('lat',$ret[0])) {
                      $mappoints[] = array('lat' => $ret[0]['lat'], 'lon' => $ret[0]['lon']);
                    }
                    echo "<td style='padding-top: 10pt;'>".$value2."</td>";
                  }
                }

                echo "</tr>\n"; 
              }
              echo "\n<script type='text/javascript'>\n";
	      echo "  var mappoints = [];\n";
              foreach ($mappoints as $key => $value) {
	         echo "     mappoints[".$key."] = new google.maps.LatLng(".$value['lat'].",".$value['lon'].");\n";
              }
              echo "  var locations = [];\n";
              foreach ($locations as $key => $value) {
	         if ($value['lat'] != "" )
  	           echo "   locations[".$key."] = { 'lat': \"". $value['lat']."\", 'lng': \"".$value['lng']."\", 'name': \"".htmlentities($value['name'])."\", 'brief': \"".htmlentities($value['brief'])."\", 'user_name': \"".htmlentities($value['user_name'])."\" };\n";
                 else {
		   echo " // no latitude information found, might be over the limit \n";
		   //print_r($value);
                 }
              }
              echo "</script>\n";
?>
         </tr>
       </tbody>
      </table>
    </div>
   </div>
  </div>
  
  <script type="text/javascript">
     jQuery(document).ready(function() {
       jQuery('.current-project').text(" for " + project_name);
     });

     var featureOpts = [
       {
          stylers: [
      	     { hue: '#890000' },
             { visibility: 'simplified' },
             { gamma: 0.5 },
             { weight: 0.5 }
          ]
       },
       {
         elementType: 'labels',
          stylers: [
            { visibility: 'off' }
          ]
       },
       {
         featureType: 'water',
         stylers: [
           { color: '#000009' }
         ]
       } 
     ];

     google.maps.event.addDomListener(window, 'load', initialize);

     var marker = [];
     var infowindows = [];
     function initialize() {
        var MY_MAPTYPE_ID = 'custom_style';
        var mapOptions = {
          center: new google.maps.LatLng(32.870676, -117.236019),
	  mapTypeControlOptions: {
	     mapTypeIds: [google.maps.MapTypeId.ROADMAP, MY_MAPTYPE_ID]
          },
          mapTypeId: MY_MAPTYPE_ID,
          zoom: 3,
	  zoomControl: false,
          scaleControl: false,
	  mapTypeControl: false,
          streetViewControl: false,
	  zoomControlOptions: {
	     style: google.maps.ZoomControlStyle.SMALL
          }
        };
        var map = new google.maps.Map(document.getElementById("map-canvas"), mapOptions);
	var pointArray = new google.maps.MVCArray(mappoints);
        heatmap = new google.maps.visualization.HeatmapLayer({
	   data: pointArray 
        });
        heatmap.set('radius', 50);
        var gradient = [
          //'rgba(0, 255, 255, 0)',
          //'rgba(0, 255, 255, 1)',
          //'rgba(0, 191, 255, 1)',
          //'rgba(0, 127, 255, 1)',
          //'rgba(0, 63, 255, 1)',
          //'rgba(0, 0, 255, 0)',
          //'rgba(0, 0, 223, 1)',
          //'rgba(0, 0, 191, 1)',
          //'rgba(0, 0, 159, 1)',
          //'rgba(0, 0, 127, 1)',
          'rgba(63, 0, 91, 0)',
          'rgba(127, 0, 63, 1)',
          'rgba(191, 0, 31, 1)',
          'rgba(255, 0, 0, 1)'
        ];
        heatmap.set('gradient', gradient);
        heatmap.setMap(map);

        var styledMapOptions = {
           name: 'at night'
        };
        var customMapType = new google.maps.StyledMapType(featureOpts, styledMapOptions);
        map.mapTypes.set(MY_MAPTYPE_ID, customMapType);

        var count = 0;
	for (var i in locations) {
	  infowindows.push(new google.maps.InfoWindow({
	     content: "<div id='content'><h4>" + locations[i].brief + "</h4>" + locations[i].user_name + "<br/>" + locations[i].name + "<br/></div>"
          }));
	  marker.push(new google.maps.Marker({
	     position: new google.maps.LatLng(parseFloat(locations[i].lat) + ((Math.random()-0.5)/1000), parseFloat(locations[i].lng) + ((Math.random()-0.5)/1000)),
	     map: map,
	     title: locations[i].name
          }));
          function openInfo(count) {
	    return function() {
  	      infowindows[count].open(map,marker[count]);
            }
          }
	  google.maps.event.addListener(marker[count], 'click', openInfo(count));
          count = count + 1;
        }

     }
  </script>

</body>
</html>
