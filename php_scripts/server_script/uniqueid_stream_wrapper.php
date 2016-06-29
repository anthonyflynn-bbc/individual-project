<?php

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

    $this->check_uniqueid_array(date('Ymd',time())); // checks if the uniqueid array is up to date
    
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
      
      $entry = str_getcsv($trimmed); //parses the CSV string into an array

      // To be of interest, the line must have 10 pieces of data and should 
      // exclude the URA Version array (must start with a '1')
      if(count($entry) == 10 && $entry[0] == 1) {
          //place quotes around strings / escape special characters

	  $entry[1] = $GLOBALS['DBH']->quote($entry[1]); // StopID
	  $entry[3] = $GLOBALS['DBH']->quote($entry[3]); // LineName
	  $entry[4] = $GLOBALS['DBH']->quote($entry[4]); // DestinationText
	  $entry[5] = $GLOBALS['DBH']->quote($entry[5]); // VehicleID
	  $entry[7] = $GLOBALS['DBH']->quote($entry[7]); // RegistrationNumber
	  $entry[8] = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$entry[8]/1000));
	  $entry[9] = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$entry[9]/1000));

	  $entry_tripid_reg = strval($entry[6]).substr($entry[7], 1, -1);
	  $entry_uniqueid;

	  $this->get_uniqueid($entry_tripid_reg, $entry_uniqueid,$entry[6],
	  	       $entry[7],$entry[3]); //get (or generate) uniqueid

	  // Set up string to insert values into stop prediction database
          $save_stop_prediction = $insert_stop_prediction."values ($entry[1],"
	  			  	."$entry[2],$entry[4],$entry[5],"
					."$entry[8],$entry[9],$current_time,"
					."$entry_uniqueid)";
	  
	  // insert stop prediction into database:
	  $this->execute_sql($save_stop_prediction);
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

      $this->execute_sql($save_uniqueid);

    } else { // uniqueid already exists in uniqueid_array
      $uniqueid = $this->uniqueid_array[$tripid_reg];
    }
  }

  // function to check whether the value of $array_date is equal to the
  // current day.  If not, the $uniqueid_array is reloaded to ensure that it
  // only ever contains two days of data
  function check_uniqueid_array($current_date) {
    if($current_date != $this->array_date) {
      $this->array_date = $current_date;
      $this->journey_daycount = 1;
      $this->load_uniqueid_array();
    }
  }
    
  // function to prepare sql statement to load uniqueids from database
  function load_uniqueid_sql($date_string) {
    return "SELECT uniqueid, tripid, registrationnumber "
    	  ."FROM journey_identifier "
          ."WHERE uniqueid LIKE '$date_string%' ";
  }

  // function to load all key-value pairs for the current day and one day 
  // before into $uniqueid_array from the database relation journey_identifier
  function load_uniqueid_array() {
    unset($this->uniqueid_array); // delete any values held in $uniqueid_array
    $this->uniqueid_array = array(); // assign a new empty array
    $previous_date = strval(intval($this->array_date)-1);

    // set up SQL statements to load uniqueid data:
    $previous_day_sql = $this->load_uniqueid_sql($previous_date);
    $current_day_sql = $this->load_uniqueid_sql($this->array_date);

    // Execute SQL and fetch results into an array indexed by column name:
    $previous_day_result = $this->execute_sql($previous_day_sql)->fetchAll(PDO::FETCH_ASSOC);
    $current_day_result = $this->execute_sql($current_day_sql)->fetchAll(PDO::FETCH_ASSOC);

    // save values into uniqueid array
    foreach($previous_day_result as $key => $value) { 
      $uniqueid = $value['uniqueid'];
      $tripid_reg = strval($value['tripid']).$value['registrationnumber'];
      $this->uniqueid_array[$tripid_reg] = $uniqueid;
    }

    if($current_day_result) { // false if no values returned
      foreach($current_day_result as $key => $value) { 
        $uniqueid = $value['uniqueid'];
        $tripid_reg = strval($value['tripid']).$value['registrationnumber'];
        $this->uniqueid_array[$tripid_reg] = $uniqueid;
        $this->journey_daycount++;
      }
    }
  }

  // Function executes an sql statement & returns an array of the response
  function execute_sql($sql_statement) {
    try {
      $database_obj = $GLOBALS['DBH']->prepare($sql_statement);
      $database_obj->execute(); // executes the prepared statement;
    }
    catch(PDOException $e) {
      echo $e->getMessage()."\n";
    }
  
    // returns executed database object:
    return $database_obj;
  }
}

// Register wrapper:
stream_wrapper_register("tflStreamWrapper","tflStreamWrapper") 
  or die("Failed to register protocol");

?>
