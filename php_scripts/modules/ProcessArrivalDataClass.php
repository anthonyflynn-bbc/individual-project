<?php

// ProcessArrivalDataClass.php
// Anthony Miles Flynn
// (10/08/16)
// ADD DESCRIPTION!


include_once '/data/individual_project/php/modules/DatabaseClass.php';

class ProcessArrivals {
  private $database;
  private $DBH;
  private $process_time_unix;
  private $arrival_table; // stop arrivals data
  private $arrival_table_backup; // stop arrivals backup table
  private $route_table; // route reference data
  private $stop_table; // stop reference data

  // Constructor
  public function __construct($process_time_unix,
			      $arrival_table = "batch_journey_all",
			      $arrival_table_backup = "batch_journey_backup",
			      $route_table = "route_reference",
			      $stop_table = "stop_reference") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->process_time_unix = $process_time_unix;
    $this->arrival_table = $arrival_table;
    $this->arrival_table_backup = $arrival_table_backup;
    $this->route_table = $route_table;
    $this->stop_table = $stop_table;
  }

  // Function to remove duplicate arrival predictions where the same 
  // uniqueid + stopid + visitnumber is processed by stop_prediction in two 
  // batches in the same second
  public function process_data() {
    $previous_day_unix = strtotime('yesterday',$this->process_time_unix);
    $relevant_uniqueids = "'".date("Ymd",$previous_day_unix)."%'"; 
    		// date for which the duplicates should be removed

    $start_time_unix = strtotime('yesterday',$this->process_time_unix);
    $end_time_unix = $start_time_unix + 24 * 60 * 60;
    $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_time_unix));
    $end_time = $this->DBH->quote(date('Y-m-d H:i:s', $end_time_unix));

    $duplicates = $this->identify_duplicates($relevant_uniqueids);
    $selected_rows = $this->select_best_results($duplicates);

    $this->make_backup($start_time, $end_time); // make backup before altering

    $this->DBH->beginTransaction();
    $this->delete_duplicates($relevant_uniqueids);
    $this->insert_best_results($selected_rows);
    $this->DBH->commit();
    echo "Remove duplicates complete.\n";

    echo "Removing negative linktimes...";
    $this->delete_negative_linktimes($start_time, $end_time);
    echo "Removal of negative linktimes complete.\n";
  }

  // Function identifies those lines in the batch database which have more than 
  // one prediction for the same uniqueid + stopid + visitnumber combination
  private function identify_duplicates($relevant_uniqueids) {
    echo "Identifying duplicate lines...";

    $sql =  "SELECT * "
    	   ."FROM "
	   ."(SELECT uniqueid, stopid, visitnumber, COUNT(recordtime) "
	   ."FROM (SELECT * FROM $this->arrival_table "
	          ."WHERE uniqueid LIKE $relevant_uniqueids) AS relevant "
	   ."GROUP BY uniqueid, stopid, visitnumber "
	   ."HAVING COUNT(recordtime) > 1) AS duplicates "
	   ."JOIN $this->arrival_table USING(uniqueid) "
	   ."WHERE $this->arrival_table.stopid = duplicates.stopid";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function processes the duplicate results to identify the best arrival data.
  private function select_best_results($duplicates) {
    echo "Selecting best results...";
    $selected_rows = array();

    foreach($duplicates as $entry) {
      $stop_array_key = $entry['uniqueid'].$entry['stopid'].$entry['visitnumber'];

      $details = array('stopid'=>$entry['stopid'],
		       'visitnumber'=>$entry['visitnumber'],
		       'destinationtext'=>$entry['destinationtext'],
		       'vehicleid'=>$entry['vehicleid'],
		       'estimatedtime'=>$entry['estimatedtime'],
		       'expiretime'=>$entry['expiretime'],
		       'recordtime'=>$entry['recordtime'],
		       'uniqueid'=>$entry['uniqueid']);

      if(!array_key_exists($stop_array_key, $selected_rows)) {
        $selected_rows[$stop_array_key] = $details;
      } elseif($details['expiretime'] == '1970-01-01 01:00:00' || 
      	      ($details['estimatedtime'] > $selected_rows[$stop_array_key]['estimatedtime'] &&
	       $selected_rows[$stop_array_key]['expiretime'] != '1970-01-01 01:00:00')) {
	unset($selected_rows[$stop_array_key]);
	$selected_rows[$stop_array_key] = $details;
      }
    }
  echo "Complete.\n";
  return $selected_rows;
  }

  // Function deletes any duplicate lines from the batch database
  private function delete_duplicates($relevant_uniqueids) {
    echo "Deleting duplicate rows...";

    $sql = "DELETE FROM $this->arrival_table "
    	  ."WHERE (uniqueid, stopid, visitnumber) IN ( "
	  ."SELECT uniqueid, stopid, visitnumber "
	  ."FROM (SELECT * FROM $this->arrival_table "
	        ."WHERE uniqueid LIKE $relevant_uniqueids) AS relevant "   
	  	."GROUP BY uniqueid, stopid, visitnumber "
	  	."HAVING COUNT(recordtime) > 1)";

    $this->database->execute_sql($sql);
    echo "Complete.\n";
  }

  // Function inserts the best arrival data (for those with duplicates) into the 
  // batch database 
  private function insert_best_results($selected_rows) {
    echo "Inserting correct rows...";
    $sql = "INSERT INTO $this->arrival_table (stopid,visitnumber,destinationtext,vehicleid,estimatedtime,expiretime,recordtime,uniqueid) "
          ."VALUES (:stopid, :visitnumber, :destinationtext, :vehicleid, :estimatedtime, :expiretime, :recordtime, :uniqueid)";

    $insert_rows = $this->DBH->prepare($sql);

    foreach($selected_rows as $entry) {
      $insert_rows->bindValue(':stopid', $entry['stopid'],PDO::PARAM_STR);
      $insert_rows->bindValue(':visitnumber', $entry['visitnumber'],PDO::PARAM_INT);
      $insert_rows->bindValue(':destinationtext', $entry['destinationtext'],PDO::PARAM_STR);
      $insert_rows->bindValue(':vehicleid', $entry['vehicleid'],PDO::PARAM_STR);
      $insert_rows->bindValue(':estimatedtime', $entry['estimatedtime'],PDO::PARAM_STR);
      $insert_rows->bindValue(':expiretime', $entry['expiretime'],PDO::PARAM_STR);
      $insert_rows->bindValue(':recordtime', $entry['recordtime'],PDO::PARAM_STR);
      $insert_rows->bindValue(':uniqueid', $entry['uniqueid'],PDO::PARAM_STR);
      $insert_rows->execute();
    }
  }

  // Function moves data which has already been used to calculate link times to a backup
  // database to ensure database queries remain manageable as data expands
  private function make_backup($start_time, $end_time) {
    echo "Copying data to backup database...";

    $sql = "INSERT INTO $this->arrival_table_backup (stopid,visitnumber,destinationtext,vehicleid,estimatedtime,expiretime,recordtime,uniqueid) "
          ."SELECT * "
	  ."FROM $this->arrival_table "
	  ."WHERE estimatedtime BETWEEN $start_time AND $end_time";

    $this->database->execute_sql($sql);
    echo "Complete\n";
  }

  // Function deletes negative linktimes from the batch journey database
  private function delete_negative_linktimes($start_time, $end_time) {
    echo "Deleting negative linktimes...";
    $sql = "DELETE FROM $this->arrival_table "
    	  ."WHERE (uniqueid, stopid) IN "
	  ."(SELECT uniqueid, destination.stopid AS end "
	  ."FROM $this->arrival_table AS source JOIN $this->arrival_table AS destination USING(uniqueid), "
	  ."(SELECT DISTINCT a.stopid as x, b.stopid as y "
	  ."FROM ($this->route_table NATURAL JOIN $this->stop_table) AS a JOIN "
	  ."($this->route_table NATURAL JOIN $this->stop_table) AS b "
	  ."ON a.linename = b.linename "
	  ."AND a.directionid = b.directionid "
	  ."AND (b.stopnumber - a.stopnumber) = 1) as connected_stops "
	  ."WHERE source.estimatedtime BETWEEN $start_time AND $end_time "
	  ."AND x = source.stopid "
	  ."AND y = destination.stopid "
	  ."AND EXTRACT(EPOCH FROM AGE(destination.estimatedtime, source.estimatedtime)) < 0)";

    $this->database->execute_sql($sql);
  }
}


?>