<?php
// Process to be run daily after 06:00am (so that full previous day data included)
// Updates the average link time for each of the 24 hours of the previous day

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class LinkTimeCalculator {
  protected $database;
  protected $DBH; // database connection
  protected $backup_time_unix;

  // Constructor
  function __construct($backup_time_unix) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->backup_time_unix = $backup_time_unix;
  }

  // Function to execute an update of the the link time data
  function complete_update() {
    echo "Updating link times table...";
    // Extract the relevant date for which to complete update
    $start_time_unix = strtotime('yesterday',$this->backup_time_unix);
    $backup_date = $this->DBH->quote(date('Y-m-d', $start_time_unix));

    for($hod = 0; $hod < 24; $hod++) {
      $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_time_unix));
      $end_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_time_unix + 60 * 60));
      $journey_times = $this->extract_journey_times($start_time, $end_time);
      $this->insert_into_database($journey_times, $backup_date, $hod);
      $start_time_unix = $start_time_unix + 60 * 60;
      echo "Update complete for hour ".$hod."\n";
    }
    $this->make_backup();
    echo "Link times update complete.\n";
  }

  // Function moves data which has already been used to calculate link times to a backup
  // database to ensure database queries remain manageable as data expands
  function make_backup() {
    echo "Copying data to backup database...";
    $start_time_unix = strtotime('yesterday',$this->backup_time_unix);
    $end_time_unix = $start_time_unix + 24 * 60 * 60;
    $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_time_unix));
    $end_time = $this->DBH->quote(date('Y-m-d H:i:s', $end_time_unix));

    $insert_sql = "INSERT INTO batch_journey_backup (stopid,visitnumber,destinationtext,vehicleid,estimatedtime,expiretime,recordtime,uniqueid) "
                 ."SELECT * "
		 ."FROM batch_journey_all "
		 ."WHERE estimatedtime BETWEEN $start_time AND $end_time";

    $delete_sql = "DELETE FROM batch_journey_all "
		 ."WHERE estimatedtime BETWEEN $start_time AND $end_time";

    echo $delete_sql."\n";

    $this->DBH->beginTransaction();
    $this->database->execute_sql($insert_sql);
    //$this->database->execute_sql($delete_sql);
    $this->DBH->commit();
    echo "Complete\n";
  }

  // Function calculates the average journey time between each pair of stops which are
  // adjacent to each other on one or more bus routes
  function extract_journey_times($start_time, $end_time) {
    echo "Extracting journey times...";
    $sql = "SELECT source.stopid AS start, destination.stopid AS end, "
    	       ."AVG(EXTRACT(EPOCH FROM AGE(destination.estimatedtime, source.estimatedtime))) AS average_time "
	       ."FROM batch_journey_all AS source "
	       ."JOIN "
	       ."batch_journey_all AS destination "
	       ."USING(uniqueid), "
	       ."(SELECT DISTINCT a.stopid as x, b.stopid as y "
	       ."FROM (route_reference NATURAL JOIN stop_reference) AS a, "
	       ."(route_reference NATURAL JOIN stop_reference) AS b "
	       ."WHERE a.linename = b.linename "
	       ."AND a.directionid = b.directionid "
	       ."AND (b.stopnumber - a.stopnumber) = 1) as connected_stops "
	       ."WHERE source.estimatedtime BETWEEN $start_time AND $end_time "
	       ."AND x = source.stopid "
	       ."AND y = destination.stopid "
	       ."GROUP BY source.stopid, destination.stopid";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function inserts all of the calculated average journey times between stops
  // (contained in $journey_times) into the relevant database table
  function insert_into_database($journey_times, $backup_date, $hod) {
    echo "Inserting link times into database...";
    $sql = "INSERT INTO link_times_date (start_stopid,end_stopid,"
    	  ."hour,date,link_time) "
	  ."VALUES (:start_stopid, :end_stopid, :hour, :date, :link_time)";

    $save_time = $this->DBH->prepare($sql);

    foreach($journey_times as $entry) {
      $save_time->bindValue(':start_stopid', $entry['start'],PDO::PARAM_STR);
      $save_time->bindValue(':end_stopid', $entry['end'],PDO::PARAM_STR);
      $save_time->bindValue(':hour', $hod,PDO::PARAM_INT);
      $save_time->bindValue(':date', $backup_date);
      $save_time->bindValue(':link_time', $entry['average_time']);
      $save_time->execute();
    }
    echo "Complete\n";
  }

}


?>