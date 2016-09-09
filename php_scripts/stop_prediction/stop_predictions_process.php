<?php

// stop_predictions_process.php
// Anthony Miles Flynn
// (8/9/16)
// Process for instantiating the stream wrapper class for collecting predictions
// from the TfL feed and inserting them into the stop predictions relation.

include_once ('/data/individual_project/php/modules/stream_wrappers/'
	     .'StopPredictionStreamWrapperClass.php');
include_once ('/data/individual_project/php/modules/HttpClientClass.php');
include_once ('/data/individual_project/php/modules/DatabaseClass.php');

// Open up file handler user stream wrapper
$fp = fopen("tflStreamWrapper://tflStream","r+")
  or die("Error opening wrapper file handler");

// Create new HTTP client
$url = "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?"
      ."ReturnList=StopID,VisitNumber,LineName,DirectionID,DestinationText,"
      ."VehicleID,TripID,Registrationnumber,EstimatedTime,ExpireTime";

$Http = new HttpClient($url, $fp);
$Http->start_data_collection();

// Close connection / file handler on conclusion
$Http->close_connection();
fclose($fp);

?>
