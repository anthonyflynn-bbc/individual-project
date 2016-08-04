<?php

include '/data/individual_project/php/modules/stream_wrappers/StopPredictionStreamWrapperClass.php';
include '/data/individual_project/php/modules/HttpClientClass.php';
include '/data/individual_project/php/modules/DatabaseClass.php';

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
