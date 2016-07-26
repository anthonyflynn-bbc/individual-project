<?php

// PARAMETERS TO TWEAK:
  // How long in past look at 'current' bus arrivals
  // How long after no more updates recorded to assume arrival

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class LiveArrivals {
  protected $database;
  protected $DBH;
  protected $wait_period; // time to wait before assume no more updates to be received
  protected $stop_predictions = array(); // holds stop predictions (key = uniqueid + stopid + visitnumber)
  protected $stop_arrivals = array(); // holds arrivals (key = stopid). Each element contains an array of values: linename=>(uniqueid => arrival time)
  protected $link_times = array(); // holds linktimes between stopids.
  protected $route_sequence; // key is linename, with an array of stopids for each direction (key = directionid)
  protected $last_update_time_unix;
  protected $json_save_directory = "/data/individual_project/api/route_api/data/current/";

  // Constructor
  function __construct($wait_period) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->wait_period = $wait_period;
    $this->time_to_hold_data = 60 * 90; // 90 minutes
    $this->last_update_time_unix = time() - 0.5 * 3600;
    $this->route_sequence = $this->load_parse_json("/data/individual_project/php/route_reference/route_sequence_json/route_reference.json");
  }

  // Function cycles through stop predictions from the latest batch and attempts to extract arrivals
  function update_data() {
    while(true) {
      $start_update_time_unix = $this->last_update_time_unix;
      $this->last_update_time_unix = time();
      $start_time = $this->DBH->quote(date('Y-m-d H:i:s', $start_update_time_unix));  // get unix times in database format
      $end_time = $this->DBH->quote(date('Y-m-d H:i:s', $this->last_update_time_unix)); // get unix times in database format

      $predictions = $this->get_stop_predictions($start_time, $end_time);
      $this->extract_latest_stop_predictions($predictions);
      $this->extract_stop_arrivals();
      $this->update_linktimes();
      $this->process_linktimes();
      $this->create_json_files();
      //$this->delete_stale_information();
      sleep(2 * 60); // sleep for 2 minutes, then process the next batch
    }
  }

  // Function goes through all of the in-memory arrays and removes any records which there has been no updated prediction for 10 minutes
  function delete_stale_information() {
    // Delete old data from $stop_arrivals:
    foreach($this->stop_arrivals as $stopid=>$linename_arrivals) {
      foreach($linename_arrivals as $linename=>$uniqueid_arrivaltime) {
        foreach($uniqueid_arrivaltime as $uniqueid=>$arrival) {
          if(($this->last_update_time_unix - $arrival) > $this->time_to_hold_data) {
	    unset($this->stop_arrivals[$stopid][$linename][$uniqueid]);	  
	  }
	}
      }
    }

    // Delete old data from $link_times:
    foreach($this->link_times as $linkid=>$arrival_details) {
      foreach($arrival_details as $arrival_time=>$details) {
        if(($this->last_update_time_unix - $arrival_time) > $this->time_to_hold_data) {
	  unset($this->link_times[$linkid][$arrival_time]);	  
	}
      }
    }
  }

  // Function cycles through all routes (in both directions), extracts ordered sequence of linkids, extracts the latest information from the link_times array and 
  // generates a JSON file with a summary.  The JSON conains the most recent 5 link times (if available), as well as last 3 and last 5 averages for quick reference by 
  // the client process
  function create_json_files() {
    foreach($this->route_sequence as $linename=>$direction) {
      $route_times = array();
      foreach($direction as $directionid=>$stopid_array) {
        for($i = 0; $i < count($stopid_array) - 1; $i++) {
	  $linkid = $stopid_array[$i].$stopid_array[$i+1];
	  $times = array_fill(0, 5, -1);
	  $count = 0;
	  if(array_key_exists($linkid, $this->link_times)) {
	    foreach($this->link_times[$linkid] as $arrival_time=>$uniqueid_linktime) {
	      foreach($uniqueid_linktime as $uniqueid=>$linktime) {
	        $times[$count] = $this->link_times[$linkid][$arrival_time][$uniqueid];
	        $count++;
	      }
	    }
	    $route_times[$linkid] = $times;
	  }
	}
      }
      $fp = fopen($this->json_save_directory.$linename.".json", "w+");
      fwrite($fp, json_encode($route_times));
      if($linename == 3){ echo "\n".json_encode($route_times); }
      fclose($fp);      
    }
  }

  // Function cycles through all linkids for all routes and directions and calls the function extract_linkid_times to calculate the time taken to travel between the stopids 
  function update_linktimes() {
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

  // Function extracts link times between the start_stopid and end_stopid provided as parameters for the given linename.  This only extracts data for uniqueids for which 
  // there is a record of the bus arriving at both stopids
  function extract_linkid_times($linename, $start_stopid, $end_stopid) {
    $linkid = $start_stopid.$end_stopid;
    $end_stopid_arrivals = $this->stop_arrivals[$end_stopid][$linename];
    foreach($end_stopid_arrivals as $uniqueid=>$arrival_time) {
      if(array_key_exists($uniqueid, $this->stop_arrivals[$start_stopid][$linename])) {
        $link_time = ($this->stop_arrivals[$end_stopid][$linename][$uniqueid]
		    - $this->stop_arrivals[$start_stopid][$linename][$uniqueid]);
	$end_arrivaltime = $this->stop_arrivals[$end_stopid][$linename][$uniqueid];
	//unset($this->stop_arrivals[$start_stopid][$linename][$uniqueid]); // delete the arrival from the start_stopid array (as link time already processed)
	if($link_time > 0) {
	  $this->add_to_link_times($linkid, $end_arrivaltime, $uniqueid, $link_time);
	}
      }
    }
  }

  // Function adds the given parameters to the link_time array, deleting any duplicate information
  function add_to_link_times($linkid, $end_arrivaltime, $uniqueid, $link_time) {
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

  // Function retains just the most recent 5 entries for each linkid (to ensure the array size stays manageable), and ordered the entries by arrival time (most recent appears at array position 0)
  function process_linktimes() {
    foreach ($this->link_times as $linkid=>$details) {
      krsort($this->link_times[$linkid]);
      $this->link_times[$linkid] = array_slice ($this->link_times[$linkid], 0, 5, true);
    }
  }

  // Function cycles through the stop prediction data (provided as a parameter) and ensures that only the most
  // recent value for each key (uniqueid + stopid + visitnumber) is saved in the static array $stop_predictions
  function extract_latest_stop_predictions($predictions) {
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
      } elseif($entry['recordtime'] > $this->stop_predictions[$key]['recordtime']) {
        unset($this->stop_predictions[$key]);
	$this->stop_predictions[$key] = $details;
      }
    } 
  }

  // Function queries recent stop predictions and returns an array of selected data
  function get_stop_predictions($start_time, $end_time) {
    echo "Querying database for latest stop predictions...";
    $sql = "SELECT stopid, visitnumber, estimatedtime, recordtime, uniqueid, linename "
    	  ."FROM stop_prediction_all "
	  ."JOIN journey_identifier_all USING (uniqueid) "
	  ."WHERE recordtime BETWEEN $start_time AND $end_time ";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function cycles through the stop_prediction static array, and extracts arrivals.  This is determined by
  // the wait_period static variable (i.e. the time after which a bus is determined to have arrived if no more
  // stop predictions made by TfL
  function extract_stop_arrivals() {
    echo "Extracting stop arrivals...";
    $last_update_time = $this->DBH->quote(date('Y-m-d H:i:s', $this->last_update_time_unix));

    foreach($this->stop_predictions as $key=>$value) {
      if(($this->last_update_time_unix - strtotime($value['recordtime'])) > $this->wait_period
           && strtotime($value['estimatedtime']) < $this->last_update_time_unix) { // i.e. this is an arrival

        $uniqueid_arrival = array($value['uniqueid']=>strtotime($value['estimatedtime']));

	if(!array_key_exists($value['stopid'], $this->stop_arrivals)) { // no records for this stopid
	  $this->stop_arrivals[$value['stopid']] = array($value['linename']=>$uniqueid_arrival);
	} else {
	  if(!array_key_exists($value['linename'], $this->stop_arrivals[$value['stopid']])) { // records for stopid, but none for this linename
	    $this->stop_arrivals[$value['stopid']][$value['linename']] = $uniqueid_arrival;
	  } else { // records exist for this stopid and linename
	    if(array_key_exists($value['uniqueid'], $this->stop_arrivals[$value['stopid']][$value['linename']])) {
	      unset($this->stop_arrivals[$value['stopid']][$value['linename']][$value['uniqueid']]);
	    }
	    $this->stop_arrivals[$value['stopid']][$value['linename']] += $uniqueid_arrival;
	  }
	}
        unset($this->stop_predictions[$key]);
      }
    }
  }

  // Function opens the JSON file provided as the parameter, and parses the contents using a JSON parser
  function load_parse_json($filename) {
    $contents = file_get_contents($filename);
    return json_decode($contents, true); 
  }

}

?>