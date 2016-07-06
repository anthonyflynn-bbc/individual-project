<?php

// SET UP DATABASE CONNECTION //////////////////////////////////////////////////
$DBH = connect_to_database();

// EXTRACT UNIQUEID / STOPID PAIRS FOR WHICH THERE IS MORE THAN 1 ARRIVAL TIME /
$duplicates_sql = "SELECT DISTINCT uniqueid, stopid "
		 ."FROM (SELECT uniqueid, stopid, COUNT(stopid) AS count "
          	       ."FROM batch_journey "
		       ."GROUP BY stopid, uniqueid) AS full_list "
	  	 ."WHERE count > 1";

$duplicates_result = execute_sql($duplicates_sql)
			    ->fetchAll(PDO::FETCH_ASSOC);

// FOR EACH UNIQUEID / STOPID PAIR, EXTRACT BEST ENTRY AND UPDATE DATABASE /////
foreach($duplicates_result as $e) {
  $duplicate_entries_sql = "SELECT * "
  			  ."FROM batch_journey "
			  ."WHERE uniqueid = '".$e['uniqueid']
			  ."' AND stopid = '".$e['stopid']."'";

  $duplicate_entry_result = execute_sql($duplicate_entries_sql)
			           ->fetchAll(PDO::FETCH_ASSOC);

  $best_entry = extract_best_entry($duplicate_entry_result);

  update_database($best_entry);
}

// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

// Function updates the database based on the best entry (extracted from all
// entries for a particular uniqueid / stopid pair)
function update_database($best_entry) {
  delete_information($best_entry);
  insert_best_entry($best_entry);
  print_r($best_entry);
}

// Removes an information related to the uniqueid / stopid pair contained in
// $best_entry
function delete_information($best_entry) {
  $delete_sql = "DELETE FROM batch_journey "
  	       ."WHERE uniqueid = '".$best_entry['uniqueid']
	       ."' AND stopid = '".$best_entry['stopid']."'";

  execute_sql($delete_sql);
}

// Function inserts the details contained in the parameter $best_entry (once
// all other entries for the uniqueid / stopid pair have been deleted
function insert_best_entry($best_entry) {
  $insert_entry_sql = 
         "INSERT INTO batch_journey (stopid,visitnumber,"
    	 ."destinationtext,vehicleid,estimatedtime,"
        ."expiretime,recordtime,uniqueid) "
	."VALUES ("
	.$GLOBALS['DBH']->quote($best_entry['stopid']).","
	.$best_entry['visitnumber'].","
	.$GLOBALS['DBH']->quote($best_entry['destinationtext']).","
	.$GLOBALS['DBH']->quote($best_entry['vehicleid']).","
	.$GLOBALS['DBH']->quote($best_entry['estimatedtime']).","
	.$GLOBALS['DBH']->quote($best_entry['expiretime']).","
	.$GLOBALS['DBH']->quote($best_entry['recordtime']).","
	.$GLOBALS['DBH']->quote($best_entry['uniqueid']).")";

  execute_sql($insert_entry_sql);
}

// Function extracts the 'best' entry to keep.  If there is an entry with
// expiretime of 0, this is used, otherwise the maximum value for estimatedtime
// is used
function extract_best_entry($duplicate_entry_result) {
  $maximum_estimated_time = '1970-01-01 01:00:00';

  foreach($duplicate_entry_result as $entry) {
    if($entry['expiretime'] == '1970-01-01 01:00:00') {
      return $entry;
    } else {
      if(strtotime($entry['estimatedtime']) > strtotime($maximum_estimated_time)) {
        $maximum_estimated_time = $entry['estimatedtime'];
      }
    }
  }

  foreach($duplicate_entry_result as $entry) {
    if($entry['estimatedtime'] == $maximum_estimated_time) {
      return $entry;
    }
  }
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



?>
