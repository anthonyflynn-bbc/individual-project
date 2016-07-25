<?php

// Process current link times for the whole network (between all pairs of stops)
// Once have, can call a function (with a bus line) which compares the historical averages with the live times calculated - this can be done for all lines, then update link times again with new data, then cycle through lines to try to identify delays etc...

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
    $this->last_update_time_unix = time() - 0.5 * 3600;
    $this->route_sequence = $this->load_parse_json("/data/individual_project/php/route_reference/route_sequence_json/route_reference.json");
  }

  // Function cycles through stop predictions from the latest batch and attempts to extract arrivals
  function update_data() {
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
    //$this->delete_stale_information(); // STILL NEEDS TO BE WRITTEN
  }


  // ADD DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  function create_json_files() {
    $route_times = array();
    foreach($this->route_sequence as $linename=>$direction) {
      foreach($direction as $stopid_array) {
        for($i = 0; $i < count($stopid_array) - 1; $i++) {
	  $linkid = $stopid_array[$i].$stopid_array[$i+1];
	  $times = array_fill(0, 7, -1);
	  $count = 0;
	  foreach($this->link_times[$linkid] as $arrival_time=>$uniqueid_linktime) {
	    foreach($uniqueid_linktime as $uniqueid=>$linktime) {
	      $times[$count] = $this->link_times[$linkid][$arrival_time][$uniqueid];
	      $count++;
	    }
	    if($count >= 3) {
	      $times[5] = round(($times[0] + $times[1] + $times[2]) / 3);
	    }
	    if($count == 5) {
	      $times[6] = round(($times[3] + $times[4] + $times[5] * 3) / 5);
	    }
	  }
	  $route_times[$linkid] = $times;
	}
      }
    }
    print_r($route_times);
    //$this->save_json($route_times)
  }


  // ADD DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  function update_linktimes() {
    foreach($this->route_sequence as $linename=>$direction) {
      foreach($direction as $stopid_array) {
        for($i = 0; $i < count($stopid_array) - 1; $i++) {
	  if(array_key_exists($stopid_array[$i], $this->stop_arrivals) && array_key_exists($stopid_array[$i+1], $this->stop_arrivals)) {
	    $this->extract_linkid_times($linename, $stopid_array[$i], $stopid_array[$i+1]); 
	  }
	}
      }
    }  
  }

  // ADD DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  function extract_linkid_times($linename, $start_stopid, $end_stopid) {
    if(array_key_exists($linename, $this->stop_arrivals[$start_stopid]) && array_key_exists($linename, $this->stop_arrivals[$end_stopid])) {
      $linkid = $start_stopid.$end_stopid;
      $end_stopid_arrivals = $this->stop_arrivals[$end_stopid][$linename];
      foreach($end_stopid_arrivals as $uniqueid=>$arrival_time) {
        if(array_key_exists($uniqueid, $this->stop_arrivals[$start_stopid][$linename])) {
          $link_time = ($this->stop_arrivals[$end_stopid][$linename][$uniqueid]
		     - $this->stop_arrivals[$start_stopid][$linename][$uniqueid]);
	  $end_arrivaltime = $this->stop_arrivals[$end_stopid][$linename][$uniqueid];
	  unset($this->stop_arrivals[$start_stopid][$linename][$uniqueid]);
	  if($link_time > 0) {
	    $this->add_to_link_times($linkid, $end_arrivaltime, $uniqueid, $link_time);
	  }
        }
      }
    }
  }

  // ADD DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  function add_to_link_times($linkid, $end_arrivaltime, $uniqueid, $link_time) {
    $details = array($end_arrivaltime=>
		 array($uniqueid => $link_time));

    if(!array_key_exists($linkid, $this->link_times)) {
      $this->link_times[$linkid] = $details;
    } else {
      foreach($this->link_times[$linkid] as $arrival_time=>$uniqueid_linktime) {
	if(array_key_exists($uniqueid, $uniqueid_linktime)) {
	  unset($this->link_times['linkid'][$arrival_time][$uniqueid]);
	}
      }
      $this->link_times[$linkid] += $details;
    }
  }

  // ADD DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
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

	if(!array_key_exists($value['stopid'], $this->stop_arrivals)) {
	  $this->stop_arrivals[$value['stopid']] = array($value['linename']=>$uniqueid_arrival);
	} else {
	  if(!array_key_exists($value['linename'], $this->stop_arrivals[$value['stopid']])) {
	    $this->stop_arrivals[$value['stopid']][$value['linename']] = $uniqueid_arrival;
	  } else {
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