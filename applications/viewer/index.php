<!DOCTYPE HTML>
<html>
<head>
  <!-- Support for mobile touch devices -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1, minimal-ui">

  <!-- CSS -->

  <!-- Font awesome CSS for tool icons -->
  <link rel="stylesheet" href="css/font-awesome.min.css">

  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">

  <!-- UI CSS -->
  <link href="css/jquery-ui.min.css" rel="stylesheet">  

  <!-- Cornerstone Base CSS -->
  <link href="css/cornerstone.min.css" rel="stylesheet">

  <!-- Cornerstone Demo CSS -->
  <link href="css/cornerstoneDemo.css" rel="stylesheet">

  <style rel="stylesheet">
     .back-button {
         border-radius: 32px; border: 1px solid black; margin: 10px; position: fixed; background-color: #000;
     }
     .back-button:hover {
         background-color: #222;
     }
  </style>

<?php
  $series = "1.2.840.113619.2.374.15512023.5825816.13963.1461873929.18";
  if (isset($_GET['series'])) {
     $series = $_GET['series'];
  }
  echo ("<script> seriesToLoad = \"".$series."\"; </script>");

?>

</head>

<body>
  <div id="wrap">

    <!-- Nav bar -->
    <nav class="myNav navbar navbar-default" role="navigation">
      <div class="container-fluid">
        <div class="navbar-header">
            <!-- <img class="demo-avatar" src="../../images/user.jpg" width=32>       -->
            <button class="back-button">
              <span class="glyphicon glyphicon-menu-left" aria-hidden="true" style="color: gray; font-size: 52px; margin-top: 5px;"></span>
            </button>
        </div>
        <ul class="nav navbar-nav navbar-right">
          <!-- <li><a id="help" href="#" class="button hidden-xs">Help</a></li>
          <li><a id="about" href="#" class="button hidden-xs">About</a></li> -->
          <a class="navbar-brand" href="http://abcdstudy.org"><image src="http://abcdstudy.org/images/logo@2x.png" style="margin-top: -20px;" width=280/></a>
        </ul>
      </div>
    </nav>

    <div class='main' style="margin-top: 10px;">

      <!-- Tabs bar -->
      <ul id="tabs" class="nav nav-tabs" >
        <li class="active"><a href="#studyList" data-toggle="tab">Site Study List</a></li>
      </ul>

      <!-- Tab content -->
      <div id="tabContent" class="tab-content">
        <!-- Study list -->
        <div id="studyList" class="tab-pane active">
          <div class="row">
            <table class="col-md-12 table table-striped" style="margin-left: 5px; background-color: black;">
              <thead>
                <tr>
                  <th>Patient Name</th>
                  <th>Patient ID</th>
                  <th>Study Date</th>
		  <th>Study Time</th>
		  <th># Series</th>
                  <th>Modality</th>
                  <th>Study Description</th>
                  <th># Images</th>
                </tr>
              </thead>
              
              <tbody id="studyListData">
                <!-- Table rows get populated from the JSON studyList manifest -->
              </tbody>
            </table>
          </div>
          <div style="margin-bottom: 20px;">
            <i style="margin: 20px;">A service provided by the Data Analysis and Informatics Core of ABCD.</i>
          </div>
        </div>
      </div>

      <!-- Study viewer tab content template -->
  </div>
</div>



<!-- Javascripts -->

<!-- Include JQuery -->
<script src="js/jquery.min.js"></script>

<!-- Include JQuery UI for drag/drop -->
<script src="js/jquery-ui.min.js"></script>

<!-- Include JQuery Hammerjs adapter for mobile touch-->
<script src="js/hammer.min.js"></script>

<!-- Include Bootstrap js -->
<script src="js/bootstrap.min.js"></script>

<!-- include the cornerstone library -->
<script src="js/cornerstone.js"></script>

<!-- include the cornerstone library -->
<script src="js/cornerstoneMath.js"></script>

<!-- include the cornerstone tools library -->
<script src="js/cornerstoneTools.js"></script>

<!-- include the cornerstoneWADOImageLoader library -->
<script src="js/cornerstoneWADOImageLoader.js"></script>

<!-- try to load DICOM via http -->
<script src="js/cornerstoneHTTPImageLoader.js"></script>

<!-- include the cornerstoneWebImageLoader library -->
<!-- <script src="js/cornerstoneWebImageLoader.js"></script> -->

<!-- include the dicomParser library -->
<script src="js/dicomParser.js"></script>

<!-- include cornerstoneDemo.js -->
<script src="js/setupViewport.js"></script>
<script src="js/displayThumbnail.js"></script>
<script src="js/loadStudy.js"></script>
<script src="js/setupButtons.js"></script>
<script src="js/disableAllTools.js"></script>
<script src="js/forEachViewport.js"></script>
<script src="js/imageViewer.js"></script>
<script src="js/loadTemplate.js"></script>
<script src="js/help.js"></script>
<script src="js/about.js"></script>
<script src="js/setupViewportOverlays.js"></script>
<script src="js/cornerstoneDemo.js"></script>

<script>

jQuery(document).ready(function() {

   jQuery('.back-button').click(function() {
      window.location.replace("/");
   });

   jQuery('#tabs').on('click', '.close', function() {
      jQuery(this).parent().remove();
      // studyList should be opened again
      jQuery(jQuery('#tabs').children()[0]).find('a').click();
   });
});

</script>

</body>
</html>
