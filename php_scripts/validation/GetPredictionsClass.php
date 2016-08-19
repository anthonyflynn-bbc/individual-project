<?php

include ('/data/individual_project/php/validation/SingleJourneyPrediction.php');

class Predictions {
  private $route_sequence;
  private $predictions_array;
  private $save_filename;
  private $database;
  private $DBH;
  private $scheduled_lines; // array with all bus lines running in previous hour
  private $buses_in_median; // number of buses to use in calculating median

  // Constructor
  public function __construct($save_filename, $buses_in_median) {
    $this->route_sequence = $this->load_parse_json("tfl.doc.ic.ac.uk/routes/"
						   ."route_reference.json");
    $this->save_filename = "/data/individual_project/php/validation/"
    			  ."predictions/".$save_filename;
    $this->predictions_array = array();
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->scheduled_lines = $this->load_scheduled_lines();
    $this->buses_in_median = $buses_in_median;
  }

  // Function cycles through all bus lines, in both directions, and adds the
  // results to the class variable $predictions_array (which is saved to a file)
  public function get_data() {
    $complete_lines = 0;
    $incomplete_lines = 0;
    foreach($this->route_sequence as $linename=>$route) {
      foreach($route as $directionid=>$stopid_sequence) {
        $start_stopid;
        $end_stopid;

        if(count($stopid_sequence) > 1) { // Must be 2 stops in stop sequence
          $this->random_start_stop($stopid_sequence, $start_stopid, $end_stopid);

	  $client_process = new Client($linename, $directionid, $start_stopid, 
	  		    	       $end_stopid, $this->scheduled_lines, 
				       $this->buses_in_median);

          // array of 3 values: [0] = timetable [1] = historic [2] = current, 
	  // or false if data not available
	  $result = $client_process->get_journey_times();
	  if($result != false) {
	    $complete_lines++;
	    $this->predictions_array[] = array($linename, $directionid, 
	    			       	   $start_stopid, $end_stopid, time(), 
					   $result[0], $result[1], $result[2]);
	  } else {
	    $incomplete_lines++;
	  }
	}
      }
    }

    $this->save_prediction_array();

    echo "Lines for which predictions made = ".$complete_lines."\n";
    echo "Lines not running / incomplete data = ".$incomplete_lines."\n";    
  }

  // Function to load an array of all bus linenames which have produced a stop
  // prediction in the previous one hour
  private function load_scheduled_lines() {
    $current_time = time();
    $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $current_time - 3600));
    $end_time = $this->DBH->quote(date('Y-m-d H:i:s', $current_time));

    $sql = "SELECT DISTINCT linename "
    	  ."FROM stop_prediction_all "
	  ."NATURAL JOIN "
	  ."journey_identifier_all "
	  ."WHERE estimatedtime BETWEEN $start_time and $end_time";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_COLUMN);
  }

  // Function to save the prediction array to the file provided as a parameter in
  // the class constructor
  private function save_prediction_array() {
    $fp = fopen($this->save_filename, "w+");
    fwrite($fp, json_encode($this->predictions_array));
    fclose($fp);
  }


  // Function which produces a random start stopid and end stopid based on the
  // stopid sequence array provided as a parameters
  private function random_start_stop($stopid_sequence, &$start_stopid, 
  	  	   		     &$end_stopid) {
    $stop_number = count($stopid_sequence);

    $start_stopid_position = 0;
    $end_stopid_position = 0;

    while($end_stopid_position == $start_stopid_position) {
      // Must leave at least one stop to be destinationid
      $start_stopid_position = mt_rand(0, $stop_number - 2);
      $end_stopid_position = mt_rand($start_stopid_position, $stop_number - 1);
    }

    $start_stopid = $stopid_sequence[$start_stopid_position];
    $end_stopid = $stopid_sequence[$end_stopid_position];
  }

  // Function downloads the data at $url (provided as a parameter) and 
  // parses json
  private function load_parse_json($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // return data as string

    $contents = curl_exec($curl);
    return json_decode($contents, true);
  }
}


?>