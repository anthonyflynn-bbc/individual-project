<?php

include 'uniqueid_stream_wrapper_all_buses.php';

// SET UP DATABASE CONNECTION //////////////////////////////////////////////////
$DBH = connect_to_database();

// DOWNLOAD NEW DATA ///////////////////////////////////////////////////////////
$fp = fopen("tflStreamWrapper://tflStream","r+") // open file handler
  or die("Error opening wrapper file handler");

download_new_data($fp);


// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

// Function forms a connection to the bus_data database
function connect_to_database() {
  // Database access information:
  $database_type = 'pgsql';
  $server_ip = "";
  $database_name = "bus_data";
  $username = "";
  $password = "";

  // Create database connection:
  try {
    $DBH = new PDO("$database_type:host=$server_ip;dbname=$database_name",
    	     $username,$password);
  }
  catch(PDOException $e) {
    echo $e->getMessage()."\n";
    exit(1);
  }
  return $DBH;
}

// Function forms an HTTP connection to the TfL feed and downloads data
function download_new_data($fp) {
  $curl = curl_init(); 

  // Set cURL options:
  curl_setopt($curl, CURLOPT_URL, 
     "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?"
    ."ReturnList=StopID,VisitNumber,LineName,DirectionID,DestinationText,"
    ."VehicleID,TripID,Registrationnumber,EstimatedTime,ExpireTime");

  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); // Digest authorisation
  curl_setopt($curl, CURLOPT_USERPWD, ":"); // User details
  curl_setopt($curl, CURLOPT_TIMEOUT, 99999999); // Long-lived connection
  curl_setopt($curl, CURLOPT_FILE, $fp); // file pointer for output data

  curl_exec($curl); // start session (collect data)
  curl_close($curl); // close connection
  fclose($fp); // close file handler
}

?>
