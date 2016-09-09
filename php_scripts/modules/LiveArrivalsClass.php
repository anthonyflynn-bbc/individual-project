<?php

// LiveArrivalsClass.php
// Anthony Miles Flynn
// (08/09/16)
// Class for extracting live arrival estimates from stop predictions table and
// saving into current output JSON files directory on a per route basis.

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class LiveArrivals {
  private $database;
  private $DBH;
  private $wait_period; // time before predictions are assumed arrivals
  private $arrivals_cache; // time to hold arrivals before deleting from array
  private $stop_predictions = array(); // key = uniqueid + stopid + visitnumber
  private $stop_arrivals = array(); // key = stopid. Element: linename=>
  	  		   	    //(uniqueid => arrival time)
  private $link_times = array();    // holds linktimes between stopids.
  private $route_sequence;          // key = linename, elements = (directionid=>
  	  		      	    //array of stopids)
  private $last_update_time_unix;
  private $json_save_directory = "/data/individual_project/api/route_api/"
  	  		       	."data/current/"; 
  private $max_records_per_stop = 100;

  // Constructor
  public function __construct($wait_period=300, $arrivals_cache=3600, 
  	 	  	      $save_folder="") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->wait_period = $wait_period; // default 5 mintues
    $this->arrivals_cache = $arrivals_cache; // default 1 hour
    $this->last_update_time_unix = time() - 3600; // 1 hour loaded at start
    $this->route_sequence = $this->load_parse_json("tfl.doc.ic.ac.uk/routes/"
						   ."route_reference.json");
    $this->json_save_directory .= $save_folder; //appends save folder to directory
  }

  // Function cycles through latest stop predictions and looks for arrivals
  public function update_data() {
    while(true) {
      $start_update_time_unix = $this->last_update_time_unix;
      $this->last_update_time_unix = time();
      $start_time = $this->DBH->quote(date('Y-m-d H:i:s', 
      		    		$start_update_time_unix));  //format unix time
      $end_time = $this->DBH->quote(date('Y-m-d H:i:s', 
      		  		$this->last_update_time_unix));

      $predictions = $this->get_stop_predictions($start_time, $end_time);
      $this->extract_latest_stop_predictions($predictions);
      $this->extract_stop_arrivals();
      $this->update_linktimes();
      $this->process_linktimes();
      $this->create_json_files();
      $this->delete_stale_arrivals();
      $this->delete_stale_linktimes();
      sleep(5 * 60); // sleep for 5 minutes, then process the next batch
      echo "Complete.  Sleeping for 5 minutes\n";
    }
  }

  // Function retrieves recent stop predictions, returns array of predictions
  private function get_stop_predictions($start_time, $end_time) {
    echo "Querying database for latest stop predictions...";
    $sql = "SELECT stopid, visitnumber, estimatedtime, recordtime, "
    	  ."uniqueid, linename "
    	  ."FROM stop_prediction_all "
	  ."JOIN journey_identifier_all USING (uniqueid) "
	  ."WHERE recordtime BETWEEN $start_time AND $end_time ";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function cycles through the stop prediction data (provided as a parameter) 
  // and ensures that only the most recent value for each key 
  // (uniqueid + stopid + visitnumber) is saved in the array $stop_predictions
  private function extract_latest_stop_predictions($predictions) {
    echo "Extracting latest stop predictions...";
    foreach($predictions as $entry) {
      $key = $entry['uniqueid'].$entry['stopid'].strval($entry['visitnumber']);
      $details = array('stopid'=>$entry['stopid'],
      	      	       'visitnumber'=>$entry['visitnumber'],
		       'estimatedtime'=>$entry['estimatedtime'],
		       'recordtime'=>$entry['recordtime'],
		       'uniqueid'=>$entry['uniqueid'],
		       'linename'=>$entry['linename']);

      if(!array_key_exists($key, $this->stop_predictions)) {
        $this->stop_predictions[$key] = $details;
      } elseif($entry['recordtime'] > 
      			$this->stop_predictions[$key]['recordtime']) {
        unset($this->stop_predictions[$key]);
	$this->stop_predictions[$key] = $details;
      }
    } 
  }

  // Function cycles through the stop_prediction static array, and extracts 
  // arrivals.  This is determined by the wait_period static variable (i.e. the 
  // time after which a bus is determined to have arrived if no more stop 
  // predictions made by TfL
  private function extract_stop_arrivals() {
    echo "Extracting stop arrivals...";
    $last_update_time = $this->DBH->quote(date('Y-m-d H:i:s', 
    		      			       $this->last_update_time_unix));

    foreach($this->stop_predictions as $key=>$value) {
      if(($this->last_update_time_unix - strtotime($value['recordtime'])) > 
      				       	 	   $this->wait_period
           && strtotime($value['estimatedtime']) < 
	      		$this->last_update_time_unix) { // i.e. =arrival

        $uniqueid_arrival = array($value['uniqueid']=>
					strtotime($value['estimatedtime']));

	// if no records for stopid:
	if(!array_key_exists($value['stopid'], $this->stop_arrivals)) { 
	  $this->stop_arrivals[$value['stopid']] = 
	  		       array($value['linename']=>$uniqueid_arrival);
	} else { // records for stopid, but none for this linename
	  if(!array_key_exists($value['linename'], 
			       $this->stop_arrivals[$value['stopid']])) { 
	    $this->stop_arrivals[$value['stopid']][$value['linename']] = 
	    			 $uniqueid_arrival;
	  } else { // records exist for this stopid and linename
	    if(array_key_exists($value['uniqueid'], 
	    	 $this->stop_arrivals[$value['stopid']][$value['linename']])) {
	      unset($this->stop_arrivals[$value['stopid']][$value['linename']][$value['uniqueid']]);
	    }
	    $this->stop_arrivals[$value['stopid']][$value['linename']] += $uniqueid_arrival;
	  }
	}
        unset($this->stop_predictions[$key]);
      }
    }
  }

  // Function cycles through all linkids for all routes and directions and calls 
  // the function extract_linkid_times to calculate the time taken to travel 
  // between the stopids 
  private function update_linktimes() {
    foreach($this->route_sequence as $linename=>$direction) {
      foreach($direction as $directionid=>$stopid_array) {
        for($i = 0; $i < count($stopid_array) - 1; $i++) {
	  if(array_key_exists($stopid_array[$i], $this->stop_arrivals) && 
	     array_key_exists($stopid_array[$i+1], $this->stop_arrivals) &&
	     array_key_exists($linename, $this->stop_arrivals[$stopid_array[$i]]) &&
	     array_key_exists($linename, $this->stop_arrivals[$stopid_array[$i+1]])) {
	    $this->extract_linkid_times($linename, $stopid_array[$i], $stopid_array[$i+1]); 
	  }
	}
      }
    }  
  }

  // Function extracts link times between the start_stopid and end_stopid 
  // provided as parameters for the given linename.  This only extracts data for
  // uniqueids for which there is a record of the bus arriving at both stopids
  private function extract_linkid_times($linename, $start_stopid, $end_stopid) {
    $linkid = $start_stopid.$end_stopid;
    $end_stopid_arrivals = $this->stop_arrivals[$end_stopid][$linename];
    foreach($end_stopid_arrivals as $uniqueid=>$arrival_time) {
      if(array_key_exists($uniqueid, $this->stop_arrivals[$start_stopid][$linename])) {
        $link_time = ($this->stop_arrivals[$end_stopid][$linename][$uniqueid]
		    - $this->stop_arrivals[$start_stopid][$linename][$uniqueid]);
	$end_arrivaltime = $this->stop_arrivals[$end_stopid][$linename][$uniqueid];
	if($link_time > 0) {
	  $this->add_to_link_times($linkid, $end_arrivaltime, $uniqueid, $link_time);
	}
      }
    }
  }

  // Function adds the given parameters to the link_time array, deleting any 
  // duplicate information
  private function add_to_link_times($linkid, $end_arrivaltime, $uniqueid, $link_time) {
    $details = array($end_arrivaltime=>
		 array($uniqueid => $link_time));

    if(!array_key_exists($linkid, $this->link_times)) {
      $this->link_times[$linkid] = $details;
    } else {
      foreach($this->link_times[$linkid] as $arrival_time=>$uniqueid_linktime) {
	if(array_key_exists($uniqueid, $uniqueid_linktime)) {
	  unset($this->link_times[$linkid][$arrival_time]);
	}
      }
      $this->link_times[$linkid] += $details;
    }
  }

  // Function retains just the most recent entries for each linkid, ordered by 
  // arrival time (most recent appears at array position 0)
  private function process_linktimes() {
    foreach ($this->link_times as $linkid=>$details) {
      krsort($this->link_times[$linkid]);
      $this->link_times[$linkid] = array_slice ($this->link_times[$linkid], 0, 100, true);
    }
  }

  // Function removes any old records from stop_arrivals array
  private function delete_stale_arrivals() {
    foreach($this->stop_arrivals as $stopid=>$linename_arrivals) {
      foreach($linename_arrivals as $linename=>$uniqueid_arrivaltime) {
        foreach($uniqueid_arrivaltime as $uniqueid=>$arrival) {
          if(($this->last_update_time_unix - $arrival) > $this->arrivals_cache) {
	    unset($this->stop_arrivals[$stopid][$linename][$uniqueid]);	  
	  }
	}
      }
    }
  }

  // Function removes any old records from link_times array
  private function delete_stale_linktimes() {
    foreach($this->link_times as $linkid=>$arrival_time_array) {
      foreach($arrival_time_array as $arrival_time=>$uniqueid_array) {
        if(($this->last_update_time_unix - $arrival_time) > 2 * $this->arrivals_cache) { 
	  unset($this->link_times[$linkid][$arrival_time]);	  
	}
      }
    }
  }

  // Function cycles through all routes (in both directions), extracts ordered 
  // sequence of linkids, extracts the latest information from the link_times 
  // array and generates a JSON file with a summary.
  private function create_json_files() {
    foreach($this->route_sequence as $linename=>$direction) {
      $route_times = array();
      foreach($direction as $directionid=>$stopid_array) {
        for($i = 0; $i < count($stopid_array) - 1; $i++) {
	  $linkid = $stopid_array[$i].$stopid_array[$i+1];
	  $times = array(); // array with key = uniqueid and value = linktime
	  if(array_key_exists($linkid, $this->link_times)) {
	    foreach($this->link_times[$linkid] as $arrival_time=>$uniqueid_linktime) {

	      $save_format = array(key($uniqueid_linktime)=>array($arrival_time, 
	      		     	       $uniqueid_linktime[key($uniqueid_linktime)]));

	      $times += $save_format;
	      
	      if(count($times) >= $this->max_records_per_stop) {
	        break;
	      }
	    }
	    $route_times[$linkid] = $times;
	  }
	}
      }
      $fp = fopen($this->json_save_directory.$linename.".json", "w+");
      fwrite($fp, json_encode($route_times));
      fclose($fp);      
    }
  }

  // Function downloads the data at $url (provided as a paramaeter) and 
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