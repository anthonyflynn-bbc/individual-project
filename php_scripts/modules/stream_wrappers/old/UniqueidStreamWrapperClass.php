<?php

class tflStreamWrapper {
  protected $buff; // buffer to store partial lines during parsing
  protected $database;
  protected $DBH;
  protected $uniqueid_array; //uniqueid array (index: tripid+registrationnumber)
  protected $journey_daycount; // number of unique journeys for current day
  protected $uniqueid_array_update_time; //last update time of uniqueid_array

  // function called immediately after wrapper initialised
  function stream_open($path, $mode, $options, &$opened_path) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->uniqueid_array_update_time = time(); // set to current time
    $this->journey_daycount = 1;
    $this->load_uniqueid_array();
    return true;
  }

  // function to load all key-value pairs for the current day + last 4 hours of 
  // previous day into $uniqueid_array from database table journey_identifier
  function load_uniqueid_array() {
    unset($this->uniqueid_array); // delete any values held in $uniqueid_array
    $this->uniqueid_array = array(); // assign a new empty array
    $current_date = date('Ymd', strval($this->uniqueid_array_update_time));

    // set up SQL statements to load uniqueid data:
    $uniqueid_sql = $this->load_uniqueid_sql();

    // Execute SQL and fetch results into an array indexed by column name:
    $uniqueid_result = $this->database->execute_sql($uniqueid_sql)
			    ->fetchAll(PDO::FETCH_ASSOC);

    // save values into uniqueid array
    if($uniqueid_result) { // false if no values returned
      foreach($uniqueid_result as $key => $value) { 
        $uniqueid = $value['uniqueid'];
        $tripid_reg_directionid = strval($value['tripid']).$value['registrationnumber'].strval($value['directionid']);
        $this->uniqueid_array[$tripid_reg_directionid] = $uniqueid;
        if(substr($uniqueid,0,8) == $current_date) {
          $this->journey_daycount++;
        }
      }
    }
  }

  // function to prepare sql statement to load uniqueids from database
  function load_uniqueid_sql() {
    $previous_day_time = $this->DBH->quote(date('Y-m-d H:i:s',
         strtotime('yesterday', $this->uniqueid_array_update_time)+20*3600));

    return "SELECT uniqueid, tripid, registrationnumber, directionid "
    	  ."FROM journey_identifier_all "
          ."WHERE EXISTS "
	  ."(SELECT estimatedtime "
	  ."FROM stop_prediction_all "
	  ."WHERE journey_identifier_all.uniqueid = stop_prediction_all.uniqueid "
	  ."AND estimatedtime >= $previous_day_time) "
	  ."UNION "
	  ."SELECT uniqueid, tripid, registrationnumber, directionid "
    	  ."FROM journey_identifier_all "
          ."WHERE EXISTS "
	  ."(SELECT estimatedtime "
	  ."FROM batch_journey_all "
	  ."WHERE journey_identifier_all.uniqueid = batch_journey_all.uniqueid "
	  ."AND estimatedtime >= $previous_day_time)";

}

  // function called whenever a write operation is called by cURL
  function stream_write($data) { 
    $current_unix_time = time(); // time at start of batch
    $current_time = $this->DBH->quote(date('Y-m-d H:i:s',$current_unix_time));

    $this->check_uniqueid_array($current_unix_time); //check up to date
    $batch_stop_array = array(); //stores new stop_prediction data for batch
    $batch_uniqueid_array = array(); //stores new journey_identifier data for batch

    $stop_data = explode("\n", $data); //creates array of stop data from stream
    $stop_data_count = count($stop_data);
    
    // $buff contains the incomplete last line of data that was present when
    // the last batch of data was written to the database (or nothing if there
    // were no incomplete lines).  This is therefore prefixed to the first item
    // in the $stop_data array
    $stop_data[0] = $this->buff.$stop_data[0];
 
    // Save the last line of this batch of data to the buffer (in case it is
    // incomplete).  Will be prefixed to first item in next batch
    $this->buff = $stop_data[$stop_data_count - 1];

    unset($stop_data[$stop_data_count - 1]); //delete last item in $stop_data

    // For each stop prediction item in turn:
    for ($i=0; $i < count($stop_data); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($stop_data[$i], "[]\n\r"); 
      
      $entry = str_getcsv($trimmed); //parses the CSV string into an array

      // To be of interest, the line must have 11 pieces of data and should 
      // exclude the URA Version array (must start with a '1')
      if(count($entry) == 11 && $entry[0] == 1) {
        // Get existing uniqueid (or generate new uniqueid if does not exist)
	$entry_uniqueid = $this->get_uniqueid($entry[7],$entry[8],$entry[3],
					      $entry[4],$batch_uniqueid_array);

        $stop_array_key = $entry_uniqueid.$entry[1].strval($entry[2]); // uniqueid + stopid + visitnumber

	//add details to $batch_uniqueid_array so can be written to database
        $details = array('stopid'=>$entry[1],
      	      	         'visitnumber'=>$entry[2],
		       	 'destinationtext'=>$entry[5],
		       	 'vehicleid'=>$entry[6],
		       	 'estimatedtime'=>($this->DBH->quote(date('Y-m-d H:i:s',$entry[9]/1000))),
		       	 'expiretime'=>($this->DBH->quote(date('Y-m-d H:i:s',$entry[10]/1000))),
		       	 'recordtime'=>$current_time,
		       	 'uniqueid'=>$entry_uniqueid);

	if(!array_key_exists($stop_array_key,$batch_stop_array)) {
	  $batch_stop_array[$stop_array_key] = $details;
	} else {
	  unset($batch_stop_array[$stop_array_key]); //delete previous update
	  $batch_stop_array[$stop_array_key] = $details; // save latest update
	}
      }
    }
    $this->DBH->beginTransaction();
    $this->insert_journey_identifier($batch_uniqueid_array);
    $this->insert_stop_predictions($batch_stop_array);
    $this->DBH->commit();

    return strlen($data);
  }

  // function to check whether the $uniqueid_array contains current
  // information.  If not, the $uniqueid_array is reloaded
  function check_uniqueid_array($current_time) {
    $current_date = date('Ymd',strval($current_time));
    $array_date = date('Ymd',strval($this->uniqueid_array_update_time));
    if($current_date != $array_date) {
      $this->uniqueid_array_update_time = $current_time;
      $this->journey_daycount = 1;
      $this->load_uniqueid_array();
    }
  }

  // function loads the uniqueid from the associative array (if it already
  // exists), otherwise it creates a new uniqueid and saves it to the database
  function get_uniqueid($tripid, $registration_number, $linename, 
  	   		$directionid, &$batch_uniqueid_array) {

    $tripid_reg_directionid = strval($tripid).$registration_number.strval($directionid);

    //if tripid_reg doesn't exist as key in uniqueid_array:
    if(!array_key_exists($tripid_reg_directionid,$this->uniqueid_array)) {
      $current_date = date('Ymd',strval($this->uniqueid_array_update_time));
      $uniqueid = $current_date.str_pad(
      		    $this->journey_daycount,6,"0",STR_PAD_LEFT); //pad daycount

      //add uniqueid to array and increment journey_daycount:
      $this->uniqueid_array[$tripid_reg_directionid] = $uniqueid;
      $this->journey_daycount++;

      //add details to $batch_uniqueid_array so can be written to database
      $details = array('uniqueid'=>$uniqueid,
      	       	       'tripid'=>$tripid,
		       'registrationnumber'=>$registration_number,
		       'linename'=>$linename,
		       'directionid'=>$directionid);

      $batch_uniqueid_array[] = $details;

    } else { // uniqueid already exists in uniqueid_array
      $uniqueid = $this->uniqueid_array[$tripid_reg_directionid];
    }
    return $uniqueid;
  }

  // Function inserts the array of new journey_identifier data at the end of
  // each batch of data processed
  function insert_journey_identifier($batch_uniqueid_array) {
    //save uniqueid and associated information to database:

    $save_sql = "INSERT INTO journey_identifier_all (uniqueid,"
    	       ."tripid,registrationnumber,linename,directionid) "
	       ."VALUES (:uniqueid, :tripid, :registrationnumber, "
	       .":linename, :directionid)";

    $save_uniqueid = $this->DBH->prepare($save_sql);

    foreach($batch_uniqueid_array as $entry) {
      $save_uniqueid->bindValue(':uniqueid', $entry['uniqueid'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':tripid', $entry['tripid'],PDO::PARAM_INT);
      $save_uniqueid->bindValue(':registrationnumber', $entry['registrationnumber'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':linename', $entry['linename'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':directionid', $entry['directionid'], PDO::PARAM_INT);
      $save_uniqueid->execute();
    }
  }

  // Function inserts the array of new stop_prediction data at the end of
  // each batch of data processed
  function insert_stop_predictions($batch_stop_array) {
    $save_sql = "INSERT INTO stop_prediction_all (stopid,"
    	       ."visitnumber,destinationtext,vehicleid,estimatedtime,"
	       ."expiretime,recordtime,uniqueid) "
	       ."VALUES (:stopid, :visitnumber, :destinationtext, :vehicleid, "
	       .":estimatedtime, :expiretime, :recordtime, :uniqueid)";

    $save_uniqueid = $this->DBH->prepare($save_sql);

    foreach($batch_stop_array as $entry) {
      $save_uniqueid->bindValue(':stopid', $entry['stopid'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':visitnumber', $entry['visitnumber'],PDO::PARAM_INT);
      $save_uniqueid->bindValue(':destinationtext', $entry['destinationtext'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':vehicleid', $entry['vehicleid'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':estimatedtime', $entry['estimatedtime'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':expiretime', $entry['expiretime'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':recordtime', $entry['recordtime'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':uniqueid', $entry['uniqueid'], PDO::PARAM_STR);
      $save_uniqueid->execute();
    }    
  }
}

// Register wrapper:
stream_wrapper_register("tflStreamWrapper","tflStreamWrapper") 
  or die("Failed to register protocol");

?>