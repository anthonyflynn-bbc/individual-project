<?php

// RouteReferenceJSONUpdateClass.php
// Anthony Miles Flynn
// (1/8/16)
// Saves the route reference table data in a JSON format for access
// by the front-end application

include_once ('/data/individual_project/php/modules/DatabaseClass.php');

class RouteReferenceJSONUpdate {
  private $database;
  private $DBH;
  private $routes_array;
  private $save_location;

  // Constructor
  public function __construct($save_location="/data/individual_project/api/"
			        ."route_api/data/routes/route_reference.json") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->route_array = array();
    $this->save_location = $save_location;
  }

  // Function extracts an array of all bus routes, then generates the JSON 
  // file containing route sequence data
  public function complete_updates() {
    $bus_routes = $this->get_bus_routes();

    foreach($bus_routes as $route) {
      $route = $route['linename'];
      $directionid_1 = $this->get_route_sequence($route, 1);
      $directionid_2 = $this->get_route_sequence($route, 2);
      $this->route_array += array($route=>array("1"=>$directionid_1, 
						"2"=>$directionid_2));
      echo "Saved route ".$route."\n";
    }
    $this->save_json();
  }

  // Function extracts an array of all bus routes currently operating on 
  // the TfL network
  private function get_bus_routes() {
    $sql = "SELECT DISTINCT linename "
    	  ."FROM route_reference";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function extracts an ordered array of stopids for a particular bus route
  // traveling in a particular direction (both provided as parameters)
  private function get_route_sequence($route, $directionid) {
    $sql = "SELECT stopid "
    	  ."FROM route_reference NATURAL JOIN stop_reference "
	  ."WHERE linename = '$route' "
	  ."AND directionid = $directionid "
	  ."ORDER BY stopnumber ";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_COLUMN);
  }

  // Function saves the processed data, encoded into JSON format, to a file
  private function save_json() {
    $json_string = json_encode($this->route_array);

    $fp = fopen($this->save_location, "w+");
    fwrite($fp, $json_string);
    fclose($fp);
  }


}

?>
