y<?php
// Process to be run daily after 06:00am (so that full previous day data included)
// Updates the average link time for each of the 24 hours of the previous day

include '/data/individual_project/php/modules/DatabaseClass.php';

class LinkTimeCalculator {
  protected $database;
  protected $DBH; // database connection
  protected $backup_time;

  // Constructor
  function __construct($backup_time) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->backup_time = $backup_time;
  }

  // Function to execute an update of the the link time data
  function complete_update() {
    // Extract the relevant day of the week for which to update link times
    // N.B. php function: 6=Saturday, 7=Sunday, 1=Monday
    // 	    postgres function: 6=Saturday, 0=Sunday, 1=Monday
    $dow = date('N',strtotime('yesterday', $this->backup_time)) % 7;

    echo "Deleting stale information"."\n";
    $this->delete_stale_information($dow);
    echo "Stale information deleted.  Starting to process new average times"."\n";

    for($hod = 0; $hod < 24; $hod++) { // for each hour of the day
      $journey_times = $this->extract_journey_times($dow, $hod);
      $this->insert_into_database($journey_times, $dow, $hod);
      echo "Update complete for hour ".$hod."\n";
    }
  }

  // Function deletes stale average time information from the link_time database
  function delete_stale_information($dow) {
    $delete_sql = "DELETE FROM link_times_development "
    		 ."WHERE day = $dow ";

    $this->database->execute_sql($delete_sql);
  }

  // Function calculates the average journey time between each pair of stops which are
  // adjacent to each other on one or more bus routes
  function extract_journey_times($dow, $hod) {
    $time_sql = "SELECT source.stopid AS start, destination.stopid AS end, "
    	       ."AVG(EXTRACT(EPOCH FROM AGE(destination.estimatedtime, source.estimatedtime))) AS average_time "
	       ."FROM batch_journey AS source, "
	       ."batch_journey AS destination, "
	       ."(SELECT DISTINCT a.stopid as x, b.stopid as y "
	       ."FROM (route_reference NATURAL JOIN stop_reference) AS a, "
	       ."(route_reference NATURAL JOIN stop_reference) AS b "
	       ."WHERE a.linename = b.linename "
	       ."AND a.directionid = b.directionid "
	       ."AND (b.stopnumber - a.stopnumber) = 1) as connected_stops "
	       ."WHERE source.uniqueid = destination.uniqueid "
	       ."AND EXTRACT(DOW FROM source.estimatedtime) = $dow "
	       ."AND EXTRACT(HOUR FROM source.estimatedtime) = $hod "
	       ."AND x = source.stopid "
	       ."AND y = destination.stopid "
	       ."GROUP BY source.stopid, destination.stopid";

    return $this->database->execute_sql($time_sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function inserts all of the calculated average journey times between stops
  // (contained in $journey_times) into the relevant database table
  function insert_into_database($journey_times, $dow, $hod) {
    $save_sql = "INSERT INTO link_times_development (start_stopid,end_stopid,"
    	       ."hour,day,link_time) "
	       ."VALUES (:start_stopid, :end_stopid, :hour, :day, :link_time)";

    $save_time = $this->DBH->prepare($save_sql);

    foreach($journey_times as $entry) {
      $save_time->bindValue(':start_stopid', $entry['start'],PDO::PARAM_STR);
      $save_time->bindValue(':end_stopid', $entry['end'],PDO::PARAM_STR);
      $save_time->bindValue(':hour', $hod,PDO::PARAM_INT);
      $save_time->bindValue(':day', $dow,PDO::PARAM_INT);
      $save_time->bindValue(':link_time', $entry['average_time']);
      $save_time->execute();
    }
  }

}


?>
