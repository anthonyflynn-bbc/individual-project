<?php

// LinkTimesDayClass.php
// Anthony Miles Flynn
// (30/07/16)
// Calculates the average time taken between all consecutive stopids occurring 
// on any of the TfL network bus lines.  Averages are calculated on an hourly 
// basis, and the data is averaged across days of the week, based on the 
// historical data for all dates falling on a particular day.  The script, when 
// run, calculates the averages for the previous day to the time provided as a 
// parameter in the constructor.

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class LinkTimesDay {
  private $database;
  private $DBH; // database connection
  private $process_time_unix;
  private $route_table; // route reference data
  private $stop_table; // stop reference data
  private $link_times_date_table; // link times by date data
  private $link_times_day_table; // link times by day of week

  // Constructor
  public function __construct($process_time_unix,
			      $route_table = "route_reference",
			      $stop_table = "stop_reference",
			      $link_times_date_table = "link_times_date",
			      $link_times_day_table = "link_times_average") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->process_time_unix = $process_time_unix;
    $this->route_table = $route_table;
    $this->stop_table = $stop_table;
    $this->link_times_date_table = $link_times_date_table;
    $this->link_times_day_table = $link_times_day_table;
  }

  // Function to execute an update of the the link time average data
  public function complete_update() {
    echo "Updating link times average table...\n";

    // Extract the relevant day of the week for which to update link times
    // N.B. php: 6=Saturday, 7=Sunday, 1=Monday
    // 	    psql: 6=Saturday, 0=Sunday, 1=Monday
    $dow = date('N',strtotime('yesterday', $this->process_time_unix)) % 7;

    $this->DBH->beginTransaction();
    $this->delete_stale_information($dow);
    for($hod = 0; $hod < 24; $hod++) { // for each hour of the day
      echo "Extracting link times for hour ".$hod."...";
      $link_times = $this->extract_link_times($dow, $hod);
      $this->insert_into_database($link_times, $dow, $hod);
    }
    $this->DBH->commit();
    
    echo "Update to link times average complete.\n";
  }

  // Function deletes stale average time information from link_time_day_table
  private function delete_stale_information($dow) {
    echo "Deleting stale information...";
    $sql = "DELETE FROM $this->link_times_day_table "
    	  ."WHERE day = $dow ";

    $this->database->execute_sql($sql);
    
    echo "Complete\n";
  }

  // Function calculates the average journey time between each pair of stops 
  // which are adjacent to each other on one or more bus routes
  private function extract_link_times($dow, $hod) {
    echo "Extracting link times...";

    $sql = "SELECT start_stopid, end_stopid, AVG(link_time) AS link_time "
	  ."FROM $this->link_times_date_table, "
	  ."(SELECT DISTINCT a.stopid as x, b.stopid as y "
	  ."FROM ($this->route_table NATURAL JOIN $this->stop_table) AS a, "
	  ."($this->route_table NATURAL JOIN $this->stop_table) AS b "
	  ."WHERE a.linename = b.linename "
	  ."AND a.directionid = b.directionid "
	  ."AND (b.stopnumber - a.stopnumber) = 1) as connected_stops "
	  ."WHERE hour = $hod "
	  ."AND EXTRACT(DOW FROM date) = $dow "
	  ."AND start_stopid = x "
	  ."AND end_stopid = y "
	  ."GROUP BY start_stopid, end_stopid";
    
    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function inserts all of the calculated average journey times between stops
  // into the relevant database table
  private function insert_into_database($link_times, $dow, $hod) {
    echo "Inserting average link times into database for hour ".$hod."...";

    $sql = "INSERT INTO $this->link_times_day_table (start_stopid,end_stopid,"
    	  ."hour,day,link_time) "
	  ."VALUES (:start_stopid, :end_stopid, :hour, :day, :link_time)";

    $save_time = $this->DBH->prepare($sql);

    foreach($link_times as $entry) {
      $save_time->bindValue(':start_stopid', $entry['start_stopid'],
			    PDO::PARAM_STR);
      $save_time->bindValue(':end_stopid', $entry['end_stopid'],
			    PDO::PARAM_STR);
      $save_time->bindValue(':hour', $hod, PDO::PARAM_INT);
      $save_time->bindValue(':day', $dow, PDO::PARAM_INT);
      $save_time->bindValue(':link_time', $entry['link_time']);
      $save_time->execute();
    }
    echo "Complete\n";
  }
}


?>