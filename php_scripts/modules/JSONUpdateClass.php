<?php

include ('/data/individual_project/php/modules/DatabaseClass.php');
include ('/data/individual_project/api/route_api/JSON_generator/BusRouteTimesClass.php');

class JSONUpdate {
  protected $database;
  protected $DBH;

  // Constructor
  function __construct() {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
  }

  // Function extracts an array of all bus routes, then generated the JSON file for each
  // bus route (based on average times across each hour for each of the 7 days of the week)
  function complete_updates() {
    $bus_routes = $this->get_bus_routes();

    foreach($bus_routes as $route) {
      $route = $route['linename'];
      $json_generator = new BusRouteTimes($route);

      for($i = 0; $i < 7; $i++) {
        $link_times_array = $this->get_line_times($route, $i);
        $json_generator->process_one_day($link_times_array, $i);
      }

      $json_generator->save_json();
    }
  }

  // Function extracts and array of all bus routes currently operating on the TfL network
  function get_bus_routes() {
    $sql = "SELECT DISTINCT linename "
    	  ."FROM route_reference";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function extracts an array of average times between all stops for a particular bus route
  // based on a particular day (both provided as parameters)
  function get_line_times($route, $day) {
    $sql = "SELECT x.stopid AS start, y.stopid AS end, hour, link_time "
          ."FROM (SELECT * FROM route_reference NATURAL JOIN stop_reference) AS x "
          ."JOIN (SELECT * FROM route_reference NATURAL JOIN stop_reference) AS y USING (linename, directionid) "
      	  ."JOIN link_times_average ON x.stopid = start_stopid AND y.stopid = end_stopid "
      	  ."WHERE x.linename='".$route."' "
      	  ."AND y.stopnumber - x.stopnumber = 1 "
      	  ."AND day = ".$day;

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }
}

?>
