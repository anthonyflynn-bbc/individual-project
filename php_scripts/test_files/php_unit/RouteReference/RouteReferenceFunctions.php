<?php

// RouteReferenceFunctions.php
// Anthony Miles Flynn
// (29/07/16)
// Additional class to test some private member functions of
// RouteReferenceClass.php

include_once '/data/individual_project/php/modules/DatabaseClass.php';
include_once '/data/individual_project/php/modules/HttpClientClass.php';

class RouteReferenceFunctions {
  private $database;
  private $DBH;
  private $route_reference_table;

  // Constructor
  public function __construct($route_table="route_reference") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->route_reference_table = $route_table;
  }

  // Function returns the correct direction in words for a given TfL directionid
  public function direction_from_id($directionid) {
    if($directionid == 1) {
      return "outbound";
    } else {
      return "inbound";
    }
  }

  // Function constructs an appropriate API URL and extracts the ordered stop
  // sequence from the data returned.  This is then added to the database insert
  // array ready for insertion in the database
  public function get_stops($linename, $directionid, &$database_insert_array) {
    $direction = $this->direction_from_id($directionid);
    $api_url = "https://api.tfl.gov.uk/Line/".$linename."/Route/Sequence/"
  	      .$direction."?serviceTypes=regular,night&app_id=c02bf3c4&"
	      ."app_key=5b3139aa0ef741b65ae823475f46a8b7";

    $json_array = $this->download_json($api_url);

    $ordered_line_routes = $json_array['orderedLineRoutes'];
    $ordered_naptanid = $ordered_line_routes[0]['naptanIds']; // ordered stops

    $stop_number = 0;

    foreach($ordered_naptanid as $stopcode2) {
      $details = array('linename'=>$linename,
      	      	       'directionid'=>$directionid,
		       'stopcode2'=>$stopcode2,
		       'stopnumber'=>$stop_number);
      $database_insert_array[] = $details;
      $stop_number++;
    }
  }

  // Function inserts the array of new route reference data into the database
  public function insert_route_reference($database_insert_array) {
    $sql = "INSERT INTO $this->route_reference_table (linename,directionid,"
               ."stopcode2,stopnumber) "
	       ."VALUES (:linename, :directionid, :stopcode2, :stopnumber)";

    $save_route = $this->DBH->prepare($sql);

    foreach($database_insert_array as $entry) {
      $save_route->bindValue(':linename', $entry['linename'],
			     PDO::PARAM_STR);
      $save_route->bindValue(':directionid', $entry['directionid'],
			     PDO::PARAM_INT);
      $save_route->bindValue(':stopcode2', $entry['stopcode2'],
      			     PDO::PARAM_STR);
      $save_route->bindValue(':stopnumber', $entry['stopnumber'],
			     PDO::PARAM_INT);
      $save_route->execute();
    }
  }

  // Function deletes all old data from the route reference database
  public function delete_database_contents() {
    $sql = "DELETE FROM $this->route_reference_table";
    $this->database->execute_sql($sql);
  }

  // Function returns an array of line names from the data returned by the API
  public function get_all_linenames($json_array) {
    $all_linenames = array();

    foreach($json_array as $linename) {
      $all_linenames[] = $linename['name'];
    } 
    return $all_linenames;
  }

  // Function downloads the data from the API, saves to a file and returns the
  // data as an array
  public function download_json($api_url) {
    $fp = fopen("route_reference_data.txt","w+");

    $Http = new HttpClient($api_url, $fp);
    $Http->start_data_collection();
    $Http->close_connection();
    $downloaded_data = file_get_contents("route_reference_data.txt");
    fclose($fp); // close file handler
    return json_decode($downloaded_data, true);
  }

}

?>