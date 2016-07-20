<?php
include_once '/data/individual_project/php/modules/DatabaseClass.php';

class LinkTimeDayAverage {
  protected $database;
  protected $DBH; // database connection
  protected $backup_time_unix;

  // Constructor
  function __construct($backup_time_unix) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->backup_time_unix = $backup_time_unix;
  }

  // Function to execute an update of the the link time average data
  function complete_update() {
    echo "Updating link times average table...\n";

    // Extract the relevant day of the week for which to update link times
    // N.B. php function: 6=Saturday, 7=Sunday, 1=Monday
    // 	    postgres function: 6=Saturday, 0=Sunday, 1=Monday
    $dow = date('N',strtotime('yesterday', $this->backup_time_unix)) % 7;

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

  // Function deletes stale average time information from the link_time_average database
  function delete_stale_information($dow) {
    echo "Deleting stale information...";
    $sql = "DELETE FROM link_times_average "
    	  ."WHERE day = $dow ";

    $this->database->execute_sql($sql);
    //echo $sql."\n";
    echo "Complete\n";
  }

  // Function calculates the average journey time between each pair of stops which are
  // adjacent to each other on one or more bus routes
  function extract_link_times($dow, $hod) {
    echo "Extracting link times...";

    $sql = "SELECT start_stopid, end_stopid, AVG(link_time) AS link_time "
	  ."FROM link_times_date, "
	  ."(SELECT DISTINCT a.stopid as x, b.stopid as y "
	  ."FROM (route_reference NATURAL JOIN stop_reference) AS a, "
	  ."(route_reference NATURAL JOIN stop_reference) AS b "
	  ."WHERE a.linename = b.linename "
	  ."AND a.directionid = b.directionid "
	  ."AND (b.stopnumber - a.stopnumber) = 1) as connected_stops "
	  ."WHERE hour = $hod "
	  ."AND EXTRACT(DOW FROM date) = $dow "
	  ."AND start_stopid = x "
	  ."AND end_stopid = y "
	  ."GROUP BY start_stopid, end_stopid";
    //echo $sql."\n";
    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function inserts all of the calculated average journey times between stops
  // into the relevant database table
  function insert_into_database($link_times, $dow, $hod) {
    echo "Inserting average link times into database for hour ".$hod."...";
    $sql = "INSERT INTO link_times_average (start_stopid,end_stopid,"
    	  ."hour,day,link_time) "
	  ."VALUES (:start_stopid, :end_stopid, :hour, :day, :link_time)";

    $save_time = $this->DBH->prepare($sql);

    foreach($link_times as $entry) {
      $save_time->bindValue(':start_stopid', $entry['start_stopid'],PDO::PARAM_STR);
      $save_time->bindValue(':end_stopid', $entry['end_stopid'],PDO::PARAM_STR);
      $save_time->bindValue(':hour', $hod, PDO::PARAM_INT);
      $save_time->bindValue(':day', $dow, PDO::PARAM_INT);
      $save_time->bindValue(':link_time', $entry['link_time']);
      $save_time->execute();
    }
    echo "Complete\n";
  }
}


?>