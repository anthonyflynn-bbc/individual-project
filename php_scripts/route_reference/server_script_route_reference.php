<?php

// ADD SOMETHING TO TIE TO VERSION DATA?  OR JUST UPDATE EVERY HOUR OR SO

// SET UP DATABASE CONNECTION //////////////////////////////////////////////////
$DBH = connect_to_database();

// GET A SUMMARY OF ALL OF THE BUS LINES////////////////////////////////////////
$api_url = "https://api.tfl.gov.uk/Line/Mode/bus";
$json_array = download_json($api_url);
$linename_array = get_all_linenames($json_array);

// FOR EACH BUS LINE (IN EACH DIRECTION), EXTRACT THE ORDERED STOP SEQUENCE ////
$database_insert_array = array(); // stores info to be inserted into database

$number_linenames = count($linename_array); // total number of bus lines
$count = 0;

foreach($linename_array as $linename) {
  get_stops($linename,1); //outbound
  get_stops($linename,2); //inbound
  $count++;
  echo "saved to array: ".$linename."\n";

  // save records to database every 5 bus lines (to avoid array getting too big)
  if($count % 5 == 0 || $count == $number_linenames) {
    insert_route_reference($database_insert_array); // insert into database
    $database_insert_array = array();
  }
}

// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

// Function returns the correct direction in words for a given TfL directionid
function direction_from_id($directionid) {
  if($directionid == 1) {
    return "outbound";
  } else {
    return "inbound";
  }
}

// Function constructs an appropriate API URL and extracts the ordered stop
// sequence from the data returned.  This is then added to the database insert
// array ready for insertion in the database
function get_stops($linename, $directionid) {
  $direction = direction_from_id($directionid);
  $api_url = "https://api.tfl.gov.uk/Line/".$linename."/Route/Sequence/"
  	    .$direction."?serviceTypes=regular,night&app_id=c02bf3c4&"
	    ."app_key=5b3139aa0ef741b65ae823475f46a8b7";

  $json_array = download_json($api_url);

  $ordered_line_routes = $json_array['orderedLineRoutes'];
  $ordered_naptanid = $ordered_line_routes[0]['naptanIds']; //ordered stop list

  $stop_number = 0;

  foreach($ordered_naptanid as $stopcode2) {
    $details = array('linename'=>$linename,
      	      	     'directionid'=>$directionid,
		     'stopcode2'=>$stopcode2,
		     'stopnumber'=>$stop_number);
    $GLOBALS['database_insert_array'][] = $details;
    $stop_number++;
  }
}

// Function inserts the array of new route reference data into the database
function insert_route_reference($database_insert_array) {
  $save_sql = "INSERT INTO route_reference (linename,directionid,"
           ."stopcode2,stopnumber) "
	   ."VALUES (:linename, :directionid, :stopcode2, :stopnumber)";

  $save_route = $GLOBALS['DBH']->prepare($save_sql);

  foreach($database_insert_array as $entry) {
    $save_route->bindValue(':linename', $entry['linename'],PDO::PARAM_STR);
    $save_route->bindValue(':directionid', $entry['directionid'],PDO::PARAM_INT);
    $save_route->bindValue(':stopcode2', $entry['stopcode2'],PDO::PARAM_STR);
    $save_route->bindValue(':stopnumber', $entry['stopnumber'],PDO::PARAM_INT);
    $save_route->execute();
  }
  echo "database write\n";
}

// Function returns an array of line names from the data returned by the API
function get_all_linenames($json_array) {
  $all_linenames = array();

  foreach($json_array as $linename) {
    $all_linenames[] = $linename['name'];
  }
  return $all_linenames;
}

// Function downloads the data from the API, saves to a file and returns the
// data as an array
function download_json($api_url) {
  $fp = fopen("route_reference_data.txt","w+");

  download_new_data($fp, $api_url);

  $downloaded_data = file_get_contents("route_reference_data.txt");
  return json_decode($downloaded_data, true);
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

// Function forms an HTTP connection to the TfL and downloads the
// latest stop reference data
function download_new_data($fp, $api_url) {
  $curl = curl_init(); // Initialise cURL session:

  // Set cURL options:
  curl_setopt($curl, CURLOPT_URL, $api_url);

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
