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
  $DBH = new PDO("$database_type:host=$server_ip;dbname=$database_name",
		  $username,$password);
}
catch(PDOException $e) {
  echo $e->getMessage();
  echo "\n";
  exit(1);
}
echo "Database connection successful";


// SET UP STREAM WRAPPER //////////////////////////////////////////////

class tflStreamWrapper {
  protected $buff; // buffer to store partial lines during parsing
  protected $uniqueid_array; //uniqueid array (index: tripid+registrationnumber)
  protected $journey_daycount; // number of unique journeys for current day
  protected $array_date; //array contains data for $array_date + 1 day before

  // function called immediately after wrapper initialised
  function stream_open($path, $mode, $options, &$opened_path) {
    $this->array_date = date('Ymd',strval(time())); // set to current date
    $this->journey_daycount = 1;
    $this->load_uniqueid_array();
    return true;
  }


  // function called whenever a write operation is called by cURL
  function stream_write($data) { 
    $stop_data = explode("\n", $data); //creates array of stop data from stream
    $current_time = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',time()));

    $this->check_uniqueid_array(); // checks if the uniqueid array is up to date
    
    // $buff contains the incomplete last line of data that was present when
    // the last batch of data was written to the database (or nothing if there
    // were no incomplete lines).  This is therefore prefixed to the first item
    // in the $stop_data array
    $stop_data[0] = $this->buff.$stop_data[0];
 
    // Save the last line of this batch of data to the buffer (in case it is
    // incomplete).  Will be prefixed to first item in next batch
    $stop_data_count = count($stop_data);
    $this->buff = $stop_data[$stop_data_count - 1];
    unset($stop_data[$stop_data_count - 1]); //delete last item in $stop_data

    // Prepare generic SQL statement to insert into stop_prediction:
    $insert_stop_prediction = "INSERT INTO stop_prediction (stopid,visitnumber,"
    			     ."destinationtext,vehicleid,estimatedtime,"
			     ."expiretime,recordtime,uniqueid) ";

    // For each stop prediction item in turn:
    for ($i=0; $i < count($stop_data); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($stop_data[$i], "[]\n\r"); 
      $entry = explode(",", $trimmed); // split data into array

      // To be of interest, the line must have 10 pieces of data and should 
      // exclude the URA Version array (starts with a '4')
      if(count($entry) == 10 && $entry[0] == 1) {
          //modify strings to make compliant for database insertion
	  $this->modify_string($entry[1]); // StopID
	  $this->modify_string($entry[3]); // LineName
	  $this->modify_string($entry[4]); // DestinationText
	  $this->modify_string($entry[5]); // VehicleID
	  $this->modify_string($entry[7]); // RegistrationNumber
	  $entry[8] = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$entry[8]/1000));
	  $entry[9] = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$entry[9]/1000));

	  $entry_tripid_reg = strval($entry[6]).$entry[7]; // array key
	  $entry_uniqueid;

	  $this->get_uniqueid($entry_tripid_reg, $entry_uniqueid,$entry[6],
	  	       $entry[7],$entry[3]); //get (or generate) uniqueid

	  // Set up string to insert values into stop prediction database
          $save_stop_prediction = $insert_stop_prediction."values ($entry[1],"
	  			  	."$entry[2],$entry[4],$entry[5],"
					."$entry[8],$entry[9],$current_time,"
					."$entry_uniqueid)";
	  
	  // insert stop prediction into database:
	  try {
 	    $statement_obj = $GLOBALS['DBH']->prepare($save_stop_prediction);
	    $statement_obj->execute();
	  }
	  catch(PDOException $e) {
	    echo $e -> getMessage();
	    echo "\n";
	  }
      }
    }
    return strlen($data);
  }

  // function loads the uniqueid from the associative array (if it already
  // exists), otherwise it creates a new uniqueid and saves it to the array
  // and database
  function get_uniqueid($tripid_reg, &$uniqueid,$tripid,$reg,$linename) {
    //if tripid_reg doesn't exist as key in uniqueid_array:
    if(!array_key_exists($tripid_reg,$this->uniqueid_array)) {
      $uniqueid = $this->array_date.str_pad(
      		    $this->journey_daycount,6,"0",STR_PAD_LEFT); //pad daycount      

      //add uniqueid to array and increment journey_daycount:
      $this->uniqueid_array[$tripid_reg] = $uniqueid;
      $this->journey_daycount++;

      //save uniqueid and associated information to database:
      $save_uniqueid = "INSERT INTO journey_identifier (uniqueid,"
    		      ."tripid,registrationnumber,linename) "
		      ."values ($uniqueid,$tripid,$reg,$linename)";
	    
      try {
        $statement_obj = $GLOBALS['DBH']->prepare($save_uniqueid);
	$statement_obj->execute();
      }
      catch(PDOException $e) {
        echo $e -> getMessage();
	echo "\n";
      }
    } else { // uniqueid already exists in uniqueid_array
      $uniqueid = $this->uniqueid_array[$tripid_reg];
    }
  }

  // function to check whether the value of $array_date is equal to the
  // current day.  If not, the $uniqueid_array is reloaded to ensure that it
  // only ever contains two days of data
  function check_uniqueid_array() {
    $current_date = date('Ymd',time());
    if($current_date != $this->array_date) {
      $this->array_date = $current_date;
      $this->journey_daycount = 1;
      load_uniqueid_array();
    }
  }

  // Replace double quotes with single quotes + escape single quotes in string
  function modify_string(&$str) {
    $str = substr($str, 1, -1);
    $str = $GLOBALS['DBH']->quote($str);
  }

  // function to load all key-value pairs for the current day and one day 
  // before into $uniqueid_array from the database relation journey_identifier
  function load_uniqueid_array() {
    unset($this->uniqueid_array); // delete any values held in $uniqueid_array
    $this->uniqueid_array = array(); // assign a new empty array
    $previous_date = strval(intval($this->array_date)-1);

    // set up SQL statement to load uniqueid data:
    $load_uniqueid = "SELECT uniqueid, tripid, registrationnumber "
    	  	    ."FROM journey_identifier "
          	    ."WHERE uniqueid LIKE '$this->array_date%' "
	  	    ."UNION "
	  	    ."SELECT uniqueid, tripid, registrationnumber "
	  	    ."FROM journey_identifier "
	  	    ."WHERE uniqueid LIKE '$previous_date%'";

    try {
      $statement_obj = $GLOBALS['DBH']->prepare($load_uniqueid);
      $statement_obj->execute(); // executes the prepared statement
    }
      catch(PDOException $e) {
      echo $e->getMessage();
      echo "\n";
    }
  
    //returns an array indexed by column name:
    $result = $statement_obj->fetchAll(PDO::FETCH_ASSOC);
 
    // Loads relevant values into uniqueid_array:
    foreach($result as $key => $value) { 
      $uniqueid = $value['uniqueid'];
      $tripid_reg = strval($value['tripid']).$value['registrationnumber'];
      $this->uniqueid_array[$tripid_reg] = $uniqueid;
      
      //Extract the maximum value of journey daycount for current day:      
      if(substr($tripid_reg,0,7) == $this->array_date 
         && intval(substr($tripid_reg,8,13)) > $this->journey_daycount) {
	   $this->journey_daycount = intval(substr($tripid_reg,8,13));
      }
    }
  }
}

// Register wrapper:
stream_wrapper_register("tflStreamWrapper","tflStreamWrapper") 
  or die("Failed to register protocol");

// Open a file handler using the wrapper protocol
$fp = fopen("tflStreamWrapper://tflStream","r+")
  or die("Error opening wrapper file handler");


// SET UP cURL SESSION ////////////////////////////////////////////////

// Initialise cURL session:
$curl = curl_init(); 

// Set cURL options:
curl_setopt($curl, CURLOPT_URL, 
           "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?LineID=133"
	  .",3,N133,N3&ReturnList=StopID,VisitNumber,LineName,DestinationText,"
	  ."VehicleID,TripID,Registrationnumber,EstimatedTime,ExpireTime");

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
