<?php

// SET UP DATABASE CONNECTION /////////////////////////////////////////

// Database access information:
$database_type = 'pgsql';
$server_ip = "";
$database_name = "bus_data";
$username = "";
$password = "";


// Create database connection:
try {
  $DBH = new PDO("$database_type:host=$server_ip;dbname=$database_name",$username,$password);
}
catch(PDOException $e) {
  echo $e->getMessage();
  echo "\n";
  exit(1);
}
echo "Database connection successful";


// SET UP STREAM WRAPPER //////////////////////////////////////////////

class tflStreamWrapper {
  protected $buff; // buffer to store any partial lines during parsing
  protected $uniqueid_array; // associative array to hold uniqueids for current day and previous day (indexed by tripid+registrationnumber)
  protected $journey_daycount; // number of unique journeys for current day
  protected $array_date;

  function stream_open($path, $mode, $options, &$opened_path) {
    $this->array_date = date('Ymd',strval(time())); // uniqueid_array holds uniqueids for this date and the day before
    $this->journey_daycount = 1;
    $this->load_uniqueid_array_data();
    return true;
  } // called immediately after wrapper initialised
 
  function stream_write($data) { // caled when data written by cURL
    $stop_data = explode("\n", $data); //creates array of stop data from stream
    $current_time = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',time()));

    $this->check_uniqueid_array();
    
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

    $insert_stop_prediction = "INSERT INTO stop_prediction (stopid,visitnumber,destinationtext,vehicleid,estimatedtime,expiretime,recordtime,uniqueid) ";

    $insert_journey_identifier = "INSERT INTO journey_identifier (uniqueid,tripid,registrationnumber,linename) ";

    // For each stop_data item in turn:
    for ($i=0; $i < count($stop_data); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($stop_data[$i], "[]\n\r"); 
      $entry = explode(",", $trimmed); // split data into array

      // To be of interest, the line must have 10 pieces of data and should 
      // exclude the URA Version array (starts with a '4')
      if(count($entry) == 10 && $entry[0] == 1) {
          //modify strings to make compliant for database insertion
	  modify_string($entry[1]); // StopID
	  modify_string($entry[3]); // LineName
	  modify_string($entry[4]); // DestinationText
	  modify_string($entry[5]); // VehicleID
	  modify_string($entry[7]); // RegistrationNumber
	  $entry[8] = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$entry[8]/1000));
	  $entry[9] = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$entry[9]/1000));

	  $this_tripid_reg = strval($entry[6]).$entry[7];
	  $this_uniqueid;
	  if(!array_key_exists($this_tripid_reg,$this->uniqueid_array)) {
	    $this_uniqueid = $this->array_date.str_pad($this->journey_daycount,6,"0",STR_PAD_LEFT); // str_pad returns the input string padded up to 6 characters by adding "0" to the left 
	    $this->uniqueid_array[$this_tripid_reg] = $this_uniqueid;
	    $this->journey_daycount++;

	    $journey_identifier_sql = $insert_journey_identifier."values ($this_uniqueid,$entry[6],$entry[7],$entry[3])";
	    
	    try {
 	      $STH_JOURNEY = $GLOBALS['DBH']->prepare($journey_identifier_sql);
	      $STH_JOURNEY->execute();
	    }
	    catch(PDOException $e) {
	      echo $e -> getMessage();
	      echo "\n";
	    }
	  } else {
	    $this_uniqueid = $this->uniqueid_array[$this_tripid_reg];
	  }

	  // Set up string to insert values
          $sql = $insert_stop_prediction."values ($entry[1],$entry[2],$entry[4],$entry[5],$entry[8],$entry[9],$current_time,$this_uniqueid)";
	  
	  //echo $sql."\n";

	  try {
 	    $STH = $GLOBALS['DBH']->prepare($sql); // Prepares an SQL statement and returns a statement object
	    $STH->execute(); // executes the prepared statement
	  }
	  catch(PDOException $e) {
	    echo $e -> getMessage();
	    echo "\n";
	  }
      }
    }
    return strlen($data);
  }

  function check_uniqueid_array() {
    $current_date = date('Ymd',time());
    if($current_date != $this->array_date) {
      $this->array_date = $current_date;
      $this->journey_daycount = 1;
      load_uniqueid_array_data($this->array_date);
    }
  }

  function load_uniqueid_array_data() {
    unset($this->uniqueid_array);
    $this->uniqueid_array = array();
    $previous_date = strval(intval($this->array_date)-1);

    $sql = "SELECT uniqueid, tripid, registrationnumber FROM journey_identifier WHERE uniqueid LIKE '$this->array_date%' UNION SELECT uniqueid, tripid, registrationnumber FROM journey_identifier WHERE uniqueid LIKE '$previous_date%'";

    try {
      $STH = $GLOBALS['DBH']->prepare($sql); // Prepares SQL statement; Return statement object
      $STH->execute(); // executes the prepared statement
    }
      catch(PDOException $e) {
      echo $e -> getMessage();
      echo "\n";
    }
  
    $result=$STH->fetchAll(PDO::FETCH_ASSOC);
 
    foreach($result as $key => $value) { // Loads relevant values into uniqueid_array
      $uniqueid = $value['uniqueid'];
      $tripid_reg = strval($value['tripid']).$value['registrationnumber'];
      $this->uniqueid_array[$tripid_reg] = $uniqueid;
    }
  }

}

// Register wrapper:
stream_wrapper_register("tflStreamWrapper","tflStreamWrapper") 
  or die("Failed to register protocol");

// Open a file handler using the wrapper protocol
$fp = fopen("tflStreamWrapper://tflStream","r+")
  or die("Error opening wrapper file handler");

// Replace double quotes with single quotes, and escape any single quotes within string
function modify_string(&$str) {
  $str = substr($str, 1, -1);
  $str = $GLOBALS['DBH']->quote($str);
}




// SET UP cURL SESSION ////////////////////////////////////////////////

// Initialise cURL session:
$curl = curl_init(); 

// Set cURL options:
curl_setopt($curl, CURLOPT_URL, "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?LineID=133,3,N133,N3&ReturnList=StopID,VisitNumber,LineName,DestinationText,VehicleID,TripID,Registrationnumber,EstimatedTime,ExpireTime");

curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); // Digest authorisation

curl_setopt($curl, CURLOPT_USERPWD, ":"); // User details

curl_setopt($curl, CURLOPT_FILE, $fp); // file pointer for output data

curl_setopt($curl, CURLOPT_TIMEOUT, 99999999); // Long-lived connection


// Start the cURL session (begin collecting data):
curl_exec($curl);

// Close the connection and file handler when finished:
curl_close($curl);
fclose($fp);

?>
