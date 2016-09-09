<?php

// JSONUpdateClass.php
// Anthony Miles Flynn
// (01/08/16)
// Extracts average times between stopids for each bus route, on an hourly and
// daily basis, for each of the bus routes on the TfL network.  These are saved
// into json files (per route) at the directory location specified as a 
// parameter in the constructor.

include_once ('/data/individual_project/php/modules/DatabaseClass.php');

class JSONUpdate {
  private $database;
  private $DBH;
  private $save_directory;
  private $stop_table;
  private $route_table;
  private $link_times_day_table;

  // Constructor
  public function __construct($save_directory = "/data/individual_project/api"
  	 	  			       ."/route_api/data/historic/",
			      $stop_table = "stop_reference",
			      $route_table = "route_reference",
			      $link_times_day_table = "link_times_average") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->save_directory = $save_directory;
    $this->stop_table = $stop_table;
    $this->route_table = $route_table;
    $this->link_times_day_table = $link_times_day_table;
  }

  // Function extracts an array of all bus routes, then generates the JSON file 
  // for each bus route (based on average times across each hour for each of the
  // 7 days of the week)
  public function complete_updates() {
    $bus_routes = $this->get_bus_routes();

    foreach($bus_routes as $route) {
      $route = $route['linename'];
      echo "Updating data for route ".$route."...";
      $json_filename = $this->save_directory.$route.".json";
      $week_array = array_fill(0, 7, array()); // array elements = day of week

      for($i = 0; $i < 7; $i++) {
        $link_times_array = $this->get_line_times($route, $i);
	$week_array[$i] = $this->process_one_day($link_times_array, $i);
	echo "Day ".$i." complete.";
      }

      $this->save_json($json_filename, $week_array);
      echo " Route".$route." complete.\n";
    }
  }

  // Function processes the link time data between stops (included in the array 
  // parameter $link_times_array), for the day provided as a parameter
  private function process_one_day($link_times_array, $day) {
    $day_array = array_fill(0, 24, array());

    foreach($link_times_array as $entry) {
      $hour = $entry['hour']; // check what hour the entry is for
      $linkid = $entry['start'].$entry['end']; // extract the linkid
      $day_array[$hour][$linkid] = $entry['link_time']; // use linkid as key
    }

    return $day_array;
  }

  // Function saves the processed data in $week_array, encoded into JSON format 
  // into a file $json_filename
  private function save_json($json_filename, $week_array) {
    $json_string = json_encode($week_array);

    $fp = fopen($json_filename, "w+");
    fwrite($fp, $json_string);
    fclose($fp);
  }

  // Function extracts and array of all bus routes currently operating on the 
  // TfL network
  private function get_bus_routes() {
    $sql = "SELECT DISTINCT linename "
    	  ."FROM $this->route_table";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function extracts array of average times between all stops for a particular 
  // bus route based on a particular day (both provided as parameters)
  private function get_line_times($route, $day) {
    $sql = "SELECT x.stopid AS start, y.stopid AS end, hour, link_time "
          ."FROM (SELECT * FROM $this->route_table NATURAL JOIN $this->stop_table) AS x "
          ."JOIN (SELECT * FROM $this->route_table NATURAL JOIN $this->stop_table) AS y "
	  ."USING (linename, directionid) "
      	  ."JOIN $this->link_times_day_table "
	  ."ON x.stopid = start_stopid AND y.stopid = end_stopid "
      	  ."WHERE x.linename='".$route."' "
      	  ."AND y.stopnumber - x.stopnumber = 1 "
      	  ."AND day = ".$day;

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }
}

?>
