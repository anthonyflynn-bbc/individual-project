<?php

include '/data/individual_project/php/modules/DatabaseClass.php';

class BatchHourly {
  protected $start_time; // start of batch unix time value
  protected $end_time; // end of batch unix time value
  protected $start_time_text;
  protected $end_time_text;
  protected $database;
  protected $DBH; // database connection

  // Constructor
  function __construct($start_time, $end_time) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->start_time = $start_time;
    $this->end_time = $end_time;
    $this->start_time_text = $this->DBH->quote(date('Y-m-d H:i:s', $this->start_time));
    $this->end_time_text = $this->DBH->quote(date('Y-m-d H:i:s', $this->end_time));
  }

  // Function calls the necessary functions to complete the batch process 
  function complete_batch() {
    echo "Beginning process"."\n";
    $batch_data = $this->get_stop_arrivals(); // stop arrival data extracted
    echo "Arrival data extracted. Beginning batch_journey deletion"."\n";
    $this->delete_from_table("batch_journey_all"); // delete any old data from batch_journey
    echo "batch_journey deletion completed.  Beginning new data insertion"."\n";
    $this->insert_stop_arrivals($batch_data); // insert relevant journey stop arrivals
    echo "Data insertion completed.  Deleting old data from stop_prediction"."\n";
    $this->delete_from_table("stop_prediction_all"); // remove extracted data from stop_prediction
    echo "Batch process complete ".$this->start_time_text." ".$this->end_time_text."\n";
  }

  // Function extracts stop arrival estimates for each stopid for each uniqueid for the period
  // of interest and returns it in an array
  function get_stop_arrivals() {
    $stop_arrivals_sql = 
   	   "SELECT stop_prediction_all.* "
	  ."FROM stop_prediction_all, "
	  ."(SELECT stopid, uniqueid, MAX(recordtime) AS arrival_time "
	  ."FROM stop_prediction_all "
	  ."WHERE uniqueid IN "
	  ."(SELECT uniqueid "
	  ."FROM stop_prediction_all "
	  ."WHERE estimatedtime BETWEEN $this->start_time_text AND $this->end_time_text) "
	  ."GROUP BY stopid, uniqueid) arrivals "
	  ."WHERE stop_prediction_all.uniqueid = arrivals.uniqueid "
	  ."AND stop_prediction_all.stopid = arrivals.stopid "
	  ."AND stop_prediction_all.recordtime = arrivals.arrival_time";

    // return results of query from database
    return $this->database->execute_sql($stop_arrivals_sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function to delete any old arrival information from a table
  function delete_from_table($table_name) {
    $delete_sql = "DELETE FROM $table_name "
    	         ."WHERE uniqueid IN "
		 ."(SELECT DISTINCT uniqueid "
		 ."FROM stop_prediction_all "
		 ."WHERE estimatedtime BETWEEN $this->start_time_text AND $this->end_time_text)";

    $delete_uniqueid = $this->DBH->prepare($delete_sql);
    $delete_uniqueid->execute();
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
