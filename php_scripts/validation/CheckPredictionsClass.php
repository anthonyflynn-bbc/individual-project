<?php

// CheckPredictionsClass.php
// Anthony Miles Flynn
// (16/08/16)
// Class for extracting stop arrivals for comparison with predictions made using
// the application (for validation purposes)

include '/data/individual_project/php/modules/DatabaseClass.php';

class CheckPredictions {
  private $database;
  private $DBH;
  private $predictions_filename;
  private $results_filename;
  private $predictions_array;
  private $results_array;
  private $arrival_table;
  private $journey_identifier_table;

  // Constructor
  public function __construct($predictions_filename, $results_filename,
  	 	  	   $arrival_table = "batch_journey_all", 
			   $journey_identifier_table = "journey_identifier_all") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->predictions_filename = "/data/individual_project/php/validation"
    				  ."/predictions/".$predictions_filename;
    $this->results_filename = "/data/individual_project/php/validation"
    			          ."/results/".$results_filename;
    $this->predictions_array = $this->load_parse_json();
    $this->results_array = array();
    $this->arrival_table = $arrival_table;
    $this->journey_identifier_table = $journey_identifier_table;
  }

  // Function opens and parses the predictions made (timetable, historical and 
  // current), finds the best uniqueid for comparison (the first one following the 
  // time when the predictions were made),& creates an output file with the results
  public function check_predictions() {
    foreach($this->predictions_array as $prediction) {
      $linename = $prediction[0];
      $directionid = $prediction[1];
      $start_stopid = $prediction[2];
      $end_stopid = $prediction[3];
      $record_time = $prediction[4];
      $timetable_prediction = $prediction[5];
      $historic_prediction = $prediction[6];
      $current_prediction = $prediction[7];

      echo "Calculating for line ".$linename." and directionid ".$directionid."...";

      $uniqueid = $this->get_best_uniqueid($linename, $directionid, 
      		  			   $record_time, $start_stopid);
      $actual_time = $this->get_journey_information($uniqueid, $start_stopid, 
      		     				    $end_stopid);

      $timetable_accuracy = $timetable_prediction - $actual_time;
      $historic_accuracy = $historic_prediction - $actual_time;
      $current_accuracy = $current_prediction - $actual_time;

      $result = array($linename, $directionid, $uniqueid, $start_stopid, 
      	      	      $end_stopid, $actual_time, $timetable_accuracy, 
		      $historic_accuracy, $current_accuracy);
      $this->results_array[] = $result;
      echo "Complete.\n";
    }
    $this->save_results();
  }

  // Function saves the results to the filename provided in the class constructor
  private function save_results() {
    $fp = fopen($this->results_filename, "w+");
    fwrite($fp, json_encode($this->results_array));
  }

  // Function extracts the time taken for the journey with the uniqueid provided
  // as a parameter to travel from the start stopid and end stopid provided as 
  // parameters.  Returned as a value in seconds.
  private function get_journey_information($uniqueid, $start_stopid, $end_stopid) {
    $uniqueid = $this->DBH->quote($uniqueid);
    $start_stopid = $this->DBH->quote($start_stopid);
    $end_stopid = $this->DBH->quote($end_stopid);

    $sql = "SELECT EXTRACT(EPOCH FROM b.estimatedtime) - "
    	   	 ."EXTRACT(EPOCH FROM a.estimatedtime) AS journey_time "
    	  ."FROM $this->arrival_table AS a JOIN "
	  ."$this->arrival_table AS b "
	  ."USING (uniqueid) "
	  ."WHERE a.uniqueid = $uniqueid "
	  ."AND a.stopid = $start_stopid "
	  ."AND b.stopid = $end_stopid";

    $journey_time = $this->database->execute_sql($sql)
				->fetchAll(PDO::FETCH_COLUMN);
    return $journey_time[0];
  }

  // Function determines the best uniqueid for comparison (i.e. the first one
  // after the time that the journey time predictions were made)
  private function get_best_uniqueid($linename, $directionid, 
  	  	   		     $start_time, $start_stopid) {
    $start_time_database = $this->DBH->quote(date('Y-m-d H:i:s', $start_time));
    $linename = $this->DBH->quote($linename);
    $start_stopid = $this->DBH->quote($start_stopid);

    $sql = "SELECT uniqueid "		  
    	  ."FROM $this->arrival_table NATURAL JOIN $this->journey_identifier_table "
	  ."WHERE linename = $linename "
	  ."AND directionid = $directionid "
	  ."AND stopid = $start_stopid "
	  ."AND estimatedtime >= $start_time_database "
	  ."AND estimatedtime <= ALL( "
	  ."SELECT estimatedtime "
	  ."FROM $this->arrival_table NATURAL JOIN $this->journey_identifier_table "
	  ."WHERE linename = $linename "
	  ."AND directionid = $directionid "
	  ."AND stopid = $start_stopid "
	  ."AND estimatedtime >= $start_time_database)";

    $uniqueid = $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_COLUMN);
    return $uniqueid[0];
  }

  // Function downloads the predictions data and parses it into an array
  // parses json
  private function load_parse_json() {
    $contents = file_get_contents($this->predictions_filename);
    return json_decode($contents, true);
  }

}


?>