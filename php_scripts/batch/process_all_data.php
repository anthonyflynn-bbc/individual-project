<?php

// all uniquids with estimatedtimes before $backup_time will be processed
$backup_time = '2016-06-29 10:00:00'; 


// SET UP DATABASE CONNECTION //////////////////////////////////////////////////
$DBH = connect_to_database();

// PROCESS BATCH JOB ///////////////////////////////////////////////////////////
$all_uniqueids = get_uniqueids($backup_time);

foreach($all_uniqueids as $journey_uniqueid) {
  delete_stale_information($journey_uniqueid); // remove old data about journey
  process_journey($journey_uniqueid); // extract and insert arrival times
}


// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

// Function extracts all uniqueids from stop_prediction data except those 
// already processed and inserted into batch_journey
function get_uniqueids($backup_time) {
  $uniqueid_sql = 
   	   "SELECT DISTINCT uniqueid "
    	  ."FROM stop_prediction "
	  ."WHERE estimatedtime <= '$backup_time'";

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
				->fetchAll(PDO::FETCH_ASSOC);;

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
