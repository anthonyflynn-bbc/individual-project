<?php

$current_time = time(); // record time of start of program execution

// SET UP DATABASE CONNECTION //////////////////////////////////////////////////

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
echo "Database connection successful";

// PROCESS BATCH JOB ///////////////////////////////////////////////////////////
$start_time;
$end_time;

if(!get_times($current_time, $start_time, $end_time)) {
  echo "Error generating batch job times";
  exit(1);
}

$relevant_uniqueids = get_relevant_uniqueids($current_time, $start_time, 
		      			  $end_time);

foreach($relevant_uniqueids as $journey_uniqueid) {
  delete_stale_information($journey_uniqueid); // remove old data about journey
  process_journey($journey_uniqueid); // extract and insert arrival times
}


// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

// Function generates approriate start and end times for the batch job
// (from 6 hours before current time until 5 hours before current time)
function get_times($current_time, &$start_time, &$end_time) {
  $start_time = $current_time  - 6 * 60 * 60;
  $start_time = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$start_time));

  $end_time = $current_time  - 5 * 60 * 60;
  $end_time = $GLOBALS['DBH']->quote(date('Y-m-d H:i:s',$end_time));

  return true;
}

// Function extracts those uniqueids which have an arrival time in the one hour
// batch window, which did not have any stop arrivals in the period prior to
// the batch window (which would have been processed in the previous batch jobs)
function get_relevant_uniqueids($current_time, $start_time, $end_time) {
  $midnight_previous_day = $GLOBALS['DBH']
             ->quote(date('Y-m-d H:i:s',strtotime('yesterday', $current_time)));

  $uniqueid_sql = 
   	   "SELECT DISTINCT uniqueid "
    	  ."FROM stop_prediction "
          ."WHERE estimatedtime BETWEEN $start_time AND $end_time "
	  ."EXCEPT "
	  ."SELECT uniqueid "
	  ."FROM stop_prediction "
          ."WHERE estimatedtime BETWEEN $midnight_previous_day AND $start_time";

  // get results of query from database
  $uniqueid_result = execute_sql($uniqueid_sql)->fetchAll(PDO::FETCH_ASSOC);

  $uniqueid = array();
  foreach($uniqueid_result as $key => $value) {
    array_push($uniqueid, $value['uniqueid']);//save revelant uniqueids in array
  }

  return $uniqueid;
}

// Function to delete any old arrival information from the batch_journey
// database. Ensure no duplicate journey information
function delete_stale_information($journey_uniqueid) {
  $delete = "DELETE FROM batch_journey "
  	   ."WHERE uniqueid = '$journey_uniqueid'";

  execute_sql($delete);
}

// Function to extract the arrival estimates for each stop from all of the 
// stop predictions for a particular journey uniqueid
function process_journey($journey_uniqueid) {
  $arrival_times_sql = 
  	  "SELECT stop_prediction.* "
	 ."FROM stop_prediction, "
	   ."(SELECT stopid, MAX(recordtime) AS arrival_time "
	   ."FROM stop_prediction "
	   ."WHERE uniqueid='$journey_uniqueid' "
	   ."GROUP BY stopid) arrivals "
	 ."WHERE stop_prediction.stopid = arrivals.stopid "
	 ."AND stop_prediction.recordtime = arrivals.arrival_time "
	 ."AND stop_prediction.uniqueid='$journey_uniqueid'";

  $journey_arrival_array = execute_sql($arrival_times_sql)
				->fetchAll(PDO::FETCH_ASSOC);

  foreach($journey_arrival_array as $stop_arrival) {
    journey_database_insert($stop_arrival); //insert into batch_journey database
  }
}

// Function to insert the stop arrival estimates into the batch_journey database
function journey_database_insert($stop_arrival) {
  // Prepare generic SQL statement to insert into stop_prediction:
  $insert_stop_prediction = 
         "INSERT INTO batch_journey (stopid,visitnumber,"
    	."destinationtext,vehicleid,estimatedtime,"
        ."expiretime,recordtime,uniqueid) "
	."VALUES ("
	.$GLOBALS['DBH']->quote($stop_arrival['stopid']).","
	.$stop_arrival['visitnumber'].","
	.$GLOBALS['DBH']->quote($stop_arrival['destinationtext']).","
	.$GLOBALS['DBH']->quote($stop_arrival['vehicleid']).","
	.$GLOBALS['DBH']->quote($stop_arrival['estimatedtime']).","
	.$GLOBALS['DBH']->quote($stop_arrival['expiretime']).","
	.$GLOBALS['DBH']->quote($stop_arrival['recordtime']).","
	.$GLOBALS['DBH']->quote($stop_arrival['uniqueid']).")";

  execute_sql($insert_stop_prediction);
}

// Function executes an sql statement & returns an array containing the response
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

?>
