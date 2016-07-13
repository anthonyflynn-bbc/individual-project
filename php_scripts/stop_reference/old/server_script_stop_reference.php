<?php

include 'stop_reference_stream_wrapper.php';

// CHECK IF STOP DATA IS UP TO DATE ////////////////////////////////////////////
$previous_version = get_previous_version();

$baseversion_array = get_baseversion_array();

$current_version = get_current_version($baseversion_array);

check_version(); // exit script if stop data up to date, otherwise continues

// SET UP DATABASE CONNECTION //////////////////////////////////////////////////
$DBH = connect_to_database();


// DOWNLOAD NEW DATA ///////////////////////////////////////////////////////////
$fp = fopen("tflStreamWrapper://tflStream","r+") // open file handler
  or die("Error opening wrapper file handler");

download_new_data($fp);


// COMPARE DATABASES AND UPDATE STOP_REFERENCE TO REFLECT CHANGES ///////////////

$new_database = "stop_reference_temp";
$old_database = "stop_reference";
$stop_reference_schema = 
  "(stoppointname,stopid,stopcode1,stopcode2,stoppointtype,towards,bearing,"
 ."stoppointindicator,stoppointstate,latitude,longitude) ";


// Fetch additions and removals in new database:
$additions = get_difference($new_database, $old_database);
$removals = get_difference($old_database, $new_database);

// Add additions to stop_reference database:
foreach($additions as $new_stop) {
  make_insertion($new_stop);
}

// Remove removals from stop_reference database:
foreach($removals as $old_stop) {
  make_removal($old_stop);
}

$alterations = get_updates($new_database, $old_database);
// Make alterations to stop_reference database:
foreach($alterations as $changed_stop) {
  make_update($changed_stop);
}

save_latest_version(); // update version.txt file

// Delete any information in temp database:
$delete = "DELETE FROM stop_reference_temp"; 
execute_sql($delete);

echo "Update complete:\n";
echo count($additions)." additions\n";
echo count($removals)." removals\n";
echo count($alterations)." updates\n";

exit(1);



// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

// Function inserts any new stops into the stop_reference table
function make_insertion($stop) {
  $insertion_sql = "INSERT INTO stop_reference"
  		  .$GLOBALS['stop_reference_schema']
  		  ."values("
  		  .$GLOBALS['DBH']->quote($stop['stoppointname']).","
  		  .$GLOBALS['DBH']->quote($stop['stopid']).","
  		  .$GLOBALS['DBH']->quote($stop['stopcode1']).","
  		  .$GLOBALS['DBH']->quote($stop['stopcode2']).","
  		  .$GLOBALS['DBH']->quote($stop['stoppointtype']).","
  		  .$GLOBALS['DBH']->quote($stop['towards']).","
  		  .$stop['bearing'].","
  		  .$GLOBALS['DBH']->quote($stop['stoppointindicator']).","
  		  .$stop['stoppointstate'].","
  		  .$stop['latitude'].","
  		  .$stop['longitude'].")";

  execute_sql($insertion_sql);
}

// Function removes any old stops from the stop_reference table
function make_removal($stop) {
  $removal_sql = "DELETE FROM stop_reference "
  		."WHERE stopid = ".$GLOBALS['DBH']->quote($stop['stopid']);

  execute_sql($removal_sql);
}

// Function updates any stops in the stop_reference table where any details
// have changed
function make_update($stop) {
  $GLOBALS['DBH']->beginTransaction();
  make_removal($stop);
  make_insertion($stop);
  $GLOBALS['DBH']->commit();
}

// Function extracts the stop data for any stops where the stopid appears in
// $database1 but does not in $database2
function get_difference($database1, $database2) {
  $difference_sql = "SELECT * "
		   ."FROM $database1 "
		   ."WHERE stopid IN "
		     ."(SELECT stopid "
		     ."FROM $database1 "
		     ."EXCEPT "
		     ."SELECT stopid "
		     ."FROM $database2)";

  return execute_sql($difference_sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Function extracts the stop data for any stops where there have been any
// changes to the details related to that stop
function get_updates($database1, $database2) {
  $update_sql = "SELECT * "
	       ."FROM $database1 "
	       ."EXCEPT "
	       ."SELECT * "
	       ."FROM $database2";

  return execute_sql($update_sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Function checks whether the version data contained in stop_reference is
// up to date
function check_version() {
  if(!out_of_date()) {
    exit(0); // Data up to date - exit script          
  }
  echo "Updating data to version ".$GLOBALS['current_version']."\n";
}

// Function extracts the baseversion stamp reflecting the version of the data
// contained in stop_reference
function get_previous_version() {
  // Gets the baseversion of the most recently saved stop_reference data:
  $version_file = "/data/individual_project/php/stop_reference/"
  		 ."version/version.txt";
  $previous_version = file_get_contents($version_file);
  return trim($previous_version,"\n");
}

// Function gets the current version of baseversion from the TfL feed
function get_baseversion_array() {
  $version_url = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
	        ."ReturnList=BaseVersion";

  $version_data = file_get_contents($version_url);
  return explode("\n", $version_data); // array of URA & Baseversion
}

// Function gets the current version of the data based on the baseversion
// array downloaded from the TfL feed
function get_current_version($baseversion_array) {  
  for ($i=0; $i < count($baseversion_array); $i++) {
    //remove characters from front and back ('[',']' and newline character)
    $trimmed = trim($baseversion_array[$i], "[]\n\r"); 

    $entry = str_getcsv($trimmed); //parses the CSV string into an array

    // To be of interest, the line must start with a '3' (Baseversion array)
    if($entry[0] == 3) {
      return $entry[1];
    }
  }
  return -1;
}

// Function saves the updated baseversion stamp of the data contained in
// stop_reference to the file 'version.txt'
function save_latest_version() {
  $version_file = "/data/individual_project/php/stop_reference/"
  		 ."version/version.txt";

  $version_handle = fopen($version_file,"w");

  if($version_handle) {
    $previous_version = fwrite($version_handle, $GLOBALS['current_version']);
  } else {
    echo "Error writing to version.txt file";
  }

  if(!fclose($version_handle)) {
    echo "Error closing version.txt file";
  }
}

// Function tests if the most recently recorded version date is the latest
function out_of_date() {
  if($GLOBALS['previous_version'] == $GLOBALS['current_version']) {
    return false;
  } else {
    return true;
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

// Function forms an HTTP connection to the TfL feed and downloads the
// latest stop reference data
function download_new_data($fp) {
  $curl = curl_init(); // Initialise cURL session:

  // Set cURL options:
  curl_setopt($curl, CURLOPT_URL, 
	    "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
	   ."StopAlso=true&ReturnList=StopPointName,StopID,StopCode1,StopCode2,"
	   ."StopPointType,Towards,Bearing,StopPointIndicator,StopPointState,"
	   ."Latitude,Longitude");

  curl_setopt($curl, CURLOPT_FILE, $fp); // file pointer for output data
  curl_exec($curl); // start session (collect data)

  curl_close($curl); // close connection
  fclose($fp); // close file handler
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
