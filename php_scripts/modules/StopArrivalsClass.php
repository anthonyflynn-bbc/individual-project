<?php

// StopArrivalsClass.php
// Anthony Miles Flynn
// (29/07/16)
// Processes the data held in the prediction table, 6 hours after the prediction
// received.  For each uniqueid and stopid, extracts the entry with the most
// recent record time as representing the arrival time of the bus at that stop.

include '/data/individual_project/php/modules/DatabaseClass.php';

class StopArrivals { 
  private $start_time_unix; // start of batch process (unix time)
  private $end_time_unix; // end of batch process (unix time)
  private $start_time; // start of batch process (database time)
  private $end_time; // end of batch process (database time)
  private $database; // instance of DatabaseClass
  private $DBH; // database connection
  private $prediction_table;
  private $arrival_table;

  // Constructor
  public function __construct($start_time_unix, $end_time_unix, 
  	   	   $prediction_table = "stop_prediction_all",
		   $arrival_table = "batch_journey_all") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->start_time_unix = $start_time_unix;
    $this->end_time_unix = $end_time_unix;
    $this->start_time = $this->database_time($this->start_time_unix);
    $this->end_time = $this->database_time($this->end_time_unix);
    $this->prediction_table = $prediction_table;
    $this->arrival_table = $arrival_table;
  }

  // Function takes the unix time provided as a parameter, and returns the
  // equivalent time in Postgres timestamp format
  private function database_time($unix_time) {
    return $this->DBH->quote(date('Y-m-d H:i:s', $unix_time));
  }

  // Function calls the necessary functions to complete the process of
  // extracting stop arrivals from relevant stop predictions
  public function complete_batch() {
    echo "Extracting stop arrrivals...";
    $batch_data = $this->get_stop_arrivals(); 

    echo "Complete.\n Deleting stale information related to batch uniqueids...";
    $this->delete_from_table($this->arrival_table); 

    echo "Complete.\n Beginning new data insertion...";
    $this->insert_stop_arrivals($batch_data);

    echo "Complete.\n Deleting processed data from stop predictions table...";
    $this->delete_from_table($this->prediction_table);

    echo "Extraction of stop arrivals complete for the period "
         .$this->start_time." to ".$this->end_time."\n";
  }

  // Function extracts stop arrivals at each stopid for all uniqueids which have
  // an estimatedtime which falls in the one hour batch period.  Data is
  // returned in an array
  private function get_stop_arrivals() {
    $sql = "SELECT $this->prediction_table.* "
	  ."FROM $this->prediction_table, "
	  ."(SELECT stopid, uniqueid, visitnumber, MAX(recordtime) AS arrival_time "
	  ."FROM $this->prediction_table "
	  ."WHERE uniqueid IN "
	  ."(SELECT uniqueid "
	  ."FROM $this->prediction_table "
	  ."WHERE estimatedtime BETWEEN $this->start_time AND $this->end_time) "
	  ."GROUP BY stopid, uniqueid, visitnumber) arrivals "
	  ."WHERE $this->prediction_table.uniqueid = arrivals.uniqueid "
	  ."AND $this->prediction_table.stopid = arrivals.stopid "
	  ."AND $this->prediction_table.recordtime = arrivals.arrival_time";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function to delete any old arrival information from the table specified
  // as a parameter
  private function delete_from_table($table_name) {
    $sql = "DELETE FROM $table_name "
    	  ."WHERE uniqueid IN "
	  ."(SELECT DISTINCT uniqueid "
	  ."FROM $this->prediction_table "
	  ."WHERE estimatedtime BETWEEN $this->start_time AND $this->end_time)";

    $this->database->execute_sql($sql);
  }

  // Function inserts the array of new stop arrival data in arrivals table
  private function insert_stop_arrivals($batch_data) {
    $sql = "INSERT INTO $this->arrival_table (stopid,visitnumber,destinationtext,"
          ."vehicleid,estimatedtime,expiretime,recordtime,uniqueid) "
	  ."VALUES (:stopid, :visitnumber, :destinationtext, :vehicleid, "
	  .":estimatedtime, :expiretime, :recordtime, :uniqueid) ";

    $save_arrivals = $this->DBH->prepare($sql);

    foreach($batch_data as $entry) {
      $save_arrivals->bindValue(':stopid', $entry['stopid'],
				 PDO::PARAM_STR);
      $save_arrivals->bindValue(':visitnumber', $entry['visitnumber'], 
      				 PDO::PARAM_INT);
      $save_arrivals->bindValue(':destinationtext', $entry['destinationtext'], 
      				 PDO::PARAM_STR);
      $save_arrivals->bindValue(':vehicleid', $entry['vehicleid'],
				 PDO::PARAM_STR);
      $save_arrivals->bindValue(':estimatedtime', $entry['estimatedtime'],
				 PDO::PARAM_STR);
      $save_arrivals->bindValue(':expiretime', $entry['expiretime'],
      				 PDO::PARAM_STR);
      $save_arrivals->bindValue(':recordtime', $entry['recordtime'],
				 PDO::PARAM_STR);
      $save_arrivals->bindValue(':uniqueid', $entry['uniqueid'], 
      				 PDO::PARAM_STR);
      $save_arrivals->execute();
    }    
  }
}


?>
