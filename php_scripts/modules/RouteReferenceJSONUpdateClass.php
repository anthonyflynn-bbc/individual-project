<?php

// RouteReferenceJSONUpdateClass.php
// Anthony Miles Flynn
// (11/8/16)
// Saves the route reference table data in a JSON format for access
// by the front-end application (includes route sequences) and saves
// timetable data for each route into a separate file

include_once ('/data/individual_project/php/modules/DatabaseClass.php');

class RouteReferenceJSONUpdate {
  private $database;
  private $DBH;
  private $route_table;
  private $stop_table;
  private $route_array;
  private $timetable_array;
  private $route_save_location;
  private $timetable_save_location;

  // Constructor
  public function __construct($route_save_location="/data/individual_project/api/"
			        ."route_api/data/routes/route_reference.json",
			      $timetable_save_location="/data/individual_project/api/"
			        ."route_api/data/routes/timetables.json",
			      $route_table = "route_reference",
			      $stop_table = "stop_reference") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->route_table = $route_table;
    $this->stop_table = $stop_table;
    $this->route_array = array();
    $this->timetable_array = array();
    $this->route_save_location = $route_save_location;
    $this->timetable_save_location = $timetable_save_location;
  }

  // Function extracts an array of all bus routes, then generates the JSON 
  // file containing route sequence data
  public function complete_updates() {
    $bus_routes = $this->get_bus_routes();

    foreach($bus_routes as $route) {
      $route = $route['linename'];

      // Get route data and save into the route_array
      $route_direction_1 = $this->get_route_sequence($route, 1);
      $route_direction_2 = $this->get_route_sequence($route, 2);
      $this->route_array += array($route=>array("1"=>$route_direction_1, 
						"2"=>$route_direction_2));

      // Get timetable table and save into the timetable_array
      $timetable_direction_1 = $this->get_timetable_sequence($route, 1);
      $timetable_direction_2 = $this->get_timetable_sequence($route, 2);
      $this->timetable_array += array($route=>array("1"=>$timetable_direction_1, 
						    "2"=>$timetable_direction_2));
      echo "Saved route ".$route."\n";
    }
    $this->save_json($this->route_save_location, $this->route_array);
    $this->save_json($this->timetable_save_location, $this->timetable_array);
  }

  // Function extracts an array of all bus routes currently operating on 
  // the TfL network
  private function get_bus_routes() {
    $sql = "SELECT DISTINCT linename "
    	  ."FROM $this->route_table";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function extracts an ordered array of stopids for a particular bus route
  // traveling in a particular direction (both provided as parameters)
  private function get_route_sequence($route, $directionid) {
    $sql = "SELECT stopid "
    	  ."FROM $this->route_table NATURAL JOIN $this->stop_table "
	  ."WHERE linename = '$route' "
	  ."AND directionid = $directionid "
	  ."ORDER BY stopnumber ";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_COLUMN);
  }

  // Function extracts the time taken to travel between adjuacent stops for
  // a particular bus route, traveling in a particular direction (both provided as parameters)
  private function get_timetable_sequence($route, $directionid) {
    $sql = "SELECT x.stopid AS start, y.stopid AS end, y.timetable_linktime "
          ."FROM (SELECT * FROM $this->route_table NATURAL JOIN $this->stop_table) AS x "
          ."JOIN (SELECT * FROM $this->route_table NATURAL JOIN $this->stop_table) AS y "
	  ."USING (linename, directionid) "
      	  ."WHERE x.linename='".$route."' "
	  ."AND x.directionid=$directionid "
      	  ."AND y.stopnumber - x.stopnumber = 1 "
	  ."ORDER BY x.stopnumber";

    $result = $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $linktimes = array();

    //print_r($result);

    foreach($result as $link) {
      $linktimes += array($link['start'].$link['end']=>$link['timetable_linktime']);
    }

    return $linktimes;
  }

  // Function saves the data in $array, encoded into JSON format, to a file
  // located at $save_location
  private function save_json($json_filename, $array) {
    $json_string = json_encode($array); 

    $fp = fopen($json_filename, "w+");  
    fwrite($fp, $json_string);
    fclose($fp);
  }


}

?>
