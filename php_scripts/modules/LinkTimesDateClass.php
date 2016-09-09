<?php

// LinkTimesDateClass.php
// Anthony Miles Flynn
// (30/07/16)
// Extracts those stop arrivals which have an arrival time on the previous date
// and averages the time taken between all consective stopids occuring on any
// of the TfL network bus lines.  Averages are calculated on an hourly basis.

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class LinkTimesDate {
  private $database;
  private $DBH;
  private $process_time_unix; // time that the process is run
  private $arrival_table; // stop arrivals data
  private $route_table; // route reference data
  private $stop_table; // stop reference data
  private $link_times_date_table; // link times by date data

  // Constructor
  public function __construct($process_time_unix, 
			      $arrival_table = "batch_journey_all",
			      $route_table = "route_reference",
			      $stop_table = "stop_reference",
			      $link_times_date_table = "link_times_date") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->process_time_unix = $process_time_unix;
    $this->arrival_table = $arrival_table;
    $this->route_table = $route_table;
    $this->stop_table = $stop_table;
    $this->link_times_date_table = $link_times_date_table;
  }

  // Function to execute an update of the the link time data
  public function complete_update() {
    // Extract the relevant date for which to complete update
    $start_time_unix = strtotime('yesterday',$this->process_time_unix);
    $process_date = $this->DBH->quote(date('Y-m-d', $start_time_unix));
    echo "Updating link times table to insert data for ".$process_date."...\n";

    for($hod = 0; $hod < 24; $hod++) {
      $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_time_unix));
      $end_time = $this->DBH->quote(date('Y-m-d H:i:s', 
      		  			 $start_time_unix + 60 * 60)); //+1 hour
      $journey_times = $this->extract_journey_times($start_time, $end_time);
      $this->insert_into_database($journey_times, $process_date, $hod);
      $start_time_unix += 60 * 60; // +1 hour
      echo "Update complete for hour ".$hod."\n";
    }
    $this->delete_old_data();
    echo "Link times update complete.\n";
  }

  // Function calculates the average journey time between each pair of stops 
  // which are adjacent to each other on one or more bus routes
  public function extract_journey_times($start_time, $end_time) {
    echo "Extracting average times between links...";

    $sql = "SELECT source.stopid AS start, destination.stopid AS end, "
    	   ."ROUND(AVG(EXTRACT(EPOCH FROM AGE(destination.estimatedtime," 
	   ."source.estimatedtime)))) AS average_time "
	   ."FROM $this->arrival_table AS source "
	   ."JOIN "
	   ."$this->arrival_table AS destination "
	   ."USING(uniqueid), "
	   ."(SELECT DISTINCT a.stopid as x, b.stopid as y "
	   ."FROM ($this->route_table NATURAL JOIN $this->stop_table) AS a, "
	   ."($this->route_table NATURAL JOIN $this->stop_table) AS b "
	   ."WHERE a.linename = b.linename "
	   ."AND a.directionid = b.directionid "
	   ."AND (b.stopnumber - a.stopnumber) = 1) as connected_stops "
	   ."WHERE source.estimatedtime BETWEEN $start_time AND $end_time "
	   ."AND destination.estimatedtime BETWEEN $start_time AND $end_time "
	   ."AND x = source.stopid "
	   ."AND y = destination.stopid "
	   ."GROUP BY source.stopid, destination.stopid";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function inserts all of the calculated average journey times between stops
  // (contained in $journey_times) into the relevant database table
  private function insert_into_database($journey_times, $process_date, $hod) {
    echo "Inserting link times into database...";

    $sql = "INSERT INTO $this->link_times_date_table (start_stopid,end_stopid,"
    	  ."hour,date,link_time) "
	  ."VALUES (:start_stopid, :end_stopid, :hour, :date, :link_time)";

    $save_time = $this->DBH->prepare($sql);

    foreach($journey_times as $entry) {
      $save_time->bindValue(':start_stopid', $entry['start'],PDO::PARAM_STR);
      $save_time->bindValue(':end_stopid', $entry['end'],PDO::PARAM_STR);
      $save_time->bindValue(':hour', $hod,PDO::PARAM_INT);
      $save_time->bindValue(':date', $process_date);
      $save_time->bindValue(':link_time', $entry['average_time']);
      $save_time->execute();
    }
  }

  // Function deletes data from the arrivals database that has already 
  // been processed (to prevent it becoming too big and slowing performance
  private function delete_old_data() {
    echo "Deleting processed data...";

    $start_time_unix = strtotime('yesterday',$this->process_time_unix);
    $end_time_unix = $start_time_unix + 24 * 60 * 60;
    $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_time_unix));
    $end_time = $this->DBH->quote(date('Y-m-d H:i:s', $end_time_unix));

    $sql = "DELETE FROM $this->arrival_table "
    	  ."WHERE estimatedtime BETWEEN $start_time AND $end_time";

    $this->database->execute_sql($sql);
    echo "Complete\n";
  }
}

?>