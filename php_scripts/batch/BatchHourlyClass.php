<?php

include '/data/individual_project/php/modules/DatabaseClass.php';

class BatchHourly {
  protected $start_time; // start of batch unix time value
  protected $end_time; // end of batch unix time value
  protected $database;
  protected $DBH; // database connection

  // Constructor
  function __construct($start_time, $end_time) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->start_time = $start_time;
    $this->end_time = $end_time;
  }

  // Function calls the necessary functions to complete the batch process 
  function complete_batch() {
    $relevant_uniqueids = $this->get_relevant_uniqueids();
    $batch_data = $this->get_stop_arrivals();

    $this->delete_stale_information($relevant_uniqueids, "batch_journey_all");
    $this->insert_stop_arrivals($batch_data); // insert relevant journey stop arrivals
    $this->clean_stop_prediction();
  }

  // Function extracts those uniqueids which have an arrival time in the one hour
  // batch window, which did not have any stop arrivals in the period prior to
  // the batch window (which would have been processed in the previous batch jobs)
  function get_relevant_uniqueids() {
    $start = $this->DBH->quote(date('Y-m-d H:i:s', $this->start_time));
    $end = $this->DBH->quote(date('Y-m-d H:i:s', $this->end_time));

    $uniqueid_sql = 
   	   "SELECT DISTINCT uniqueid "
    	  ."FROM stop_prediction_all "
          ."WHERE estimatedtime BETWEEN $start AND $end "
	  ."EXCEPT "
	  ."SELECT uniqueid "
	  ."FROM batch_journey_all ";

    // return results of query from database
    return $this->database->execute_sql($uniqueid_sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function extracts stop arrival estimates for each stopid for each uniqueid for the period
  // of interest and returns it in an array
  function get_stop_arrivals() {
    $start = $this->DBH->quote(date('Y-m-d H:i:s', $this->start_time));
    $end = $this->DBH->quote(date('Y-m-d H:i:s', $this->end_time));

    $stop_arrivals_sql = 
   	   "SELECT stop_prediction_all.* "
	  ."FROM stop_prediction_all, "
	  ."(SELECT uniqueid "
	  ."FROM stop_prediction_all "
	  ."WHERE estimatedtime BETWEEN $start AND $end "
	  ."EXCEPT "
	  ."SELECT uniqueid "
	  ."FROM batch_journey_all) AS relevant, "
	  ."(SELECT stopid, uniqueid, MAX(recordtime) AS arrival_time "
	  ."FROM stop_prediction_all "
	  ."GROUP BY stopid, uniqueid) arrivals "
	  ."WHERE relevant.uniqueid = stop_prediction_all.uniqueid "
	  ."AND stop_prediction_all.uniqueid = arrivals.uniqueid "
	  ."AND stop_prediction_all.stopid = arrivals.stopid "
	  ."AND stop_prediction_all.recordtime = arrivals.arrival_time";

    // return results of query from database
    return $this->database->execute_sql($stop_arrivals_sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function to delete any old arrival information from a table
  function delete_stale_information($relevant_uniqueids, $table_name) {
    $delete_sql = "DELETE FROM $table_name "
    	         ."WHERE uniqueid = :uniqueid";

    $delete_uniqueid = $this->DBH->prepare($delete_sql);

    foreach($relevant_uniqueids as $entry) {
      $delete_uniqueid->bindValue(':uniqueid', $entry['uniqueid'],PDO::PARAM_STR);
      $delete_uniqueid->execute();
    }
  }

  // Function to delete any old arrival information from a table
  function clean_stop_prediction() {
    $start = $this->DBH->quote(date('Y-m-d H:i:s', $this->start_time));

    $delete_sql = "DELETE FROM stop_prediction_all "
    	         ."WHERE estimatedtime < $start";

    $this->database->execute_sql($delete_sql);
  }


  // Function inserts the array of new stop arrival data in current batch
  function insert_stop_arrivals($batch_data) {
    $save_sql = "INSERT INTO batch_journey_all (stopid,"
    	       ."visitnumber,destinationtext,vehicleid,estimatedtime,"
	       ."expiretime,recordtime,uniqueid) "
	       ."VALUES (:stopid, :visitnumber, :destinationtext, :vehicleid, "
	       .":estimatedtime, :expiretime, :recordtime, :uniqueid) ";

    $save_uniqueid = $this->DBH->prepare($save_sql);

    foreach($batch_data as $entry) {
      $save_uniqueid->bindValue(':stopid', $entry['stopid'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':visitnumber', $entry['visitnumber'],PDO::PARAM_INT);
      $save_uniqueid->bindValue(':destinationtext', $entry['destinationtext'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':vehicleid', $entry['vehicleid'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':estimatedtime', $entry['estimatedtime'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':expiretime', $entry['expiretime'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':recordtime', $entry['recordtime'],PDO::PARAM_STR);
      $save_uniqueid->bindValue(':uniqueid', $entry['uniqueid'], PDO::PARAM_STR);
      $save_uniqueid->execute();
    }    
  }

}


?>
