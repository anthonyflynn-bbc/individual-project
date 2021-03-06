<?php

include('/usr/share/php/adodb/adodb.inc.php'); // required for ANO objects

// SET UP DATABASE CONNECTION /////////////////////////////////////////

// Database access information:
$database_type = 'postgres';
$server_ip = "";
$database_name = "bus_data";
$username = "";
$password = "";

// Create database connection:
$db_connection = ADONewConnection("$database_type");

$db_connection->Connect($server_ip, $username, $password, $database_name)
  or die("Database connection error");
echo "Database connection successful.";


// SET UP STREAM WRAPPER //////////////////////////////////////////////

class tflStreamWrapper {
  protected $buff; // buffer to store any partial lines during parsing
  
  function stream_open($path, $mode, $options, &$opened_path) {
    return true;
  } // called immediately after wrapper initialised
 
  function stream_write($data) { // caled when data written by cURL
    $stop_data = explode("\n", $data); //creates array of stop data from stream

    // $buff contains the incomplete last line of data that was present when
    // the last batch of data was written to the database (or nothing if there
    // were no incomplete lines).  This is therefore prefixed to the first item
    // in the $stop_data array
    $stop_data[0] = $this->buff.$stop_data[0];
 
    // Save the last line of this batch of data to the buffer (in case it is
    // incomplete.  Will be prefixed to first item in next batch
    $stop_data_count = count($stop_data);
    $this->buff = $stop_data[$stop_data_count - 1];
    unset($stop_data[$stop_data_count - 1]); //delete last item in $stop_data

    $insert = "INSERT INTO development (stoppointname,stopid,visitnumber,lineid,destinationtext,vehicleid,tripid,registrationnumber,estimatedtime,expiretime) ";

    // For each stop_data item in turn:
    for ($i=0; $i < count($stop_data); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($stop_data[$i], "[]\n\r"); 
      $entry = explode(",", $trimmed); // split data into array

      // to be of interest, the line must have 11 pieces of data, should 
      // exclude the URA Version array (starts with a '4'), and only write
      // those lines which have an expiry of 0 (when bus arrives)
      
      if(count($entry) == 11 && $entry[0] == 1 && $entry[10] == 0) {
          // replace double quotes with single quotes for string, and use 
	  // pg_escape_string to escape any single quotes in data
	  modify_string($entry[1]);     	  
	  modify_string($entry[2]);
	  modify_string($entry[4]);
	  modify_string($entry[5]);
	  modify_string($entry[6]);
	  modify_string($entry[8]);

	  // Set up string to insert values
          $sql = $insert."values ($entry[1],$entry[2],$entry[3],$entry[4],$entry[5],$entry[6],$entry[7],$entry[8],$entry[9],$entry[10])";

	  // Insert new values into the database
          $GLOBALS['db_connection']->Execute($sql)
	    or die("error inserting data: ".$GLOBALS['db_connection']->ErrorMsg()."\n");
      }
    }

    return strlen($data);
  }
}

// Register wrapper:
stream_wrapper_register("tflStreamWrapper","tflStreamWrapper") 
  or die("Failed to register protocol");

// Open a file handler using the wrapper protocol
$fp = fopen("tflStreamWrapper://tflStream","r+")
  or die("Error opening wrapper file handler");

function modify_string(&$str) {
  $str = substr($str, 1, -1);
  $str = "'".pg_escape_string($str)."'";
}


// SET UP cURL SESSION ////////////////////////////////////////////////

// Initialise cURL session:
$curl = curl_init(); 

// Set cURL options:
curl_setopt($curl, CURLOPT_URL, "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?LineID=133,3,N133,N3&ReturnList=Stoppointname,StopID,VisitNumber,LineID,DestinationText,VehicleID,TripID,RegistrationNumber,EstimatedTime,ExpireTime");

curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); // Digest authorisation

curl_setopt($curl, CURLOPT_USERPWD, ""); // User details

curl_setopt($curl, CURLOPT_FILE, $fp); // file pointer for output data

curl_setopt($curl, CURLOPT_TIMEOUT, 99999999); // Long-lived connection


// Start the cURL session (begin collecting data):
curl_exec($curl);

// Close the connection and file handler when finished:
curl_close($curl);
fclose($fp);

?>
