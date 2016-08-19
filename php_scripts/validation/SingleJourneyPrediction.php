<?php

// SingleJourneyPrediction.php
// Anthony Miles Flynn
// (19/8/16)
// Client class for extracting historic journey times and current journey times
// based on the line, direction, start stop and end stop provided in the constructor

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class Client {
  private $linename;
  private $directionid;
  private $start_stopid;
  private $end_stopid;
  private $timetable_data;
  private $historical_data;
  private $current_data;
  private $route_sequence;
  private $scheduled_lines; // array holding all bus lines running in the past hour
  private $buses_in_median; // number of buses to use in calculating median
  private $start_time; // start time of journey being predicted

  // Constructor
  public function __construct($linename, $directionid, $start_stopid, $end_stopid, $scheduled_lines, $buses_in_median) {
    $this->linename = $linename;
    $this->directionid = $directionid;
    $this->start_stopid = $start_stopid;
    $this->end_stopid = $end_stopid;
    $this->timetable_data = $this->load_parse_json("tfl.doc.ic.ac.uk/routes/"
						   ."timetables.json");
    $this->historical_data = $this->load_parse_json("tfl.doc.ic.ac.uk/historic/"
						   .$linename.".json");
    $this->current_data = $this->load_parse_json("tfl.doc.ic.ac.uk/current/test" // CHANGED!!!!!!!!!!!!!!!!!!!!!!!
						   .$linename.".json");
    $this->route_sequence = $this->load_parse_json("tfl.doc.ic.ac.uk/routes/"
						   ."route_reference.json");
    $this->journey_times = array();
    $this->scheduled_lines = $scheduled_lines;
    $this->buses_in_median = $buses_in_median;
    $this->start_time = time();
  }

  // Function extracts timetable, current and historical estimates
  public function get_journey_times() {
    echo "Linename: ".$this->linename.", directionid: ".$this->directionid.": ";
    if($this->check_scheduled() == false) {
      echo $this->linename." not scheduled to run\n";
      return false;
    }

    $route_linkids = $this->load_linkid_sequence(); // loads links between start and end stops

    $times = array(); // 3 elements, [0] = timetable time, [1] = historic time, [2] = current time

    $times[0] = $this->calculate_timetable_journeytime($route_linkids);
    if($times[0] == false) { // Incomplete timetable data not available - ignore bus for validation purposes
      echo "Timetable data missing.\n";
      return false;
    }

    $times[1] = $this->calculate_historical_journeytime($route_linkids, $this->start_time);
    if($times[1] == false) { 
      echo "Too many missing historical link times.\n";
      return false;
    }

    $times[2] = $this->calculate_current_journeytime($route_linkids);
    if($times[2] == false) { 
      echo "Insufficient current data to form accurate prediction\n";
      return false;
    }
    else {
      $times[2] += $times[1];
    }
    echo "Complete\n";
    return $times;
  }

  // Function checks if buses on this line are direction are currently scheduled to run.
  // Returns true if a bus is scheduled; otherwise false
  private function check_scheduled() {
    if(in_array($this->linename, $this->scheduled_lines)) {
      return true;
    } else {
      return false;
    }
  }    

  // Loads an array of all linkids between the class variables $start_stopid and $end_stopid
  private function load_linkid_sequence() {
    $ordered_stopids = $this->route_sequence[$this->linename][$this->directionid];

    $start_position = array_search($this->start_stopid, $ordered_stopids);
    $end_position = array_search($this->end_stopid, $ordered_stopids);

    if($start_position === false || $end_position === false) {
      echo "Error - stops do not appear on this bus route\n";
      return;
    }

    $route_linkids = array();

    for($i=$start_position; $i < $end_position; $i++) {
      $route_linkids[] = $ordered_stopids[$i].$ordered_stopids[$i+1];
    }
    return $route_linkids;
  }

  // Calculate the media of the most recent journeys provided as a parameter
  private function calculate_median($journey_times) {
    rsort($journey_times); // sorts array in reverse order
    $middle = round(count($journey_times) / 2); 
    return $journey_times[$middle-1]; 
  }

  // Extracts the timetable time taken to each of the stopids on the line
  // (returned as an array).  If there are any missing links, a false value
  // is returned
  private function calculate_timetable_journeytime($route_linkids) {
    $timetable_time = 0;

    foreach($route_linkids as $linkid) {
      if(array_key_exists($this->linename, $this->timetable_data) &&
      	 array_key_exists($this->directionid, $this->timetable_data[$this->linename]) && 
	 array_key_exists($linkid, $this->timetable_data[$this->linename][$this->directionid])) {
	$timetable_time += $this->timetable_data[$this->linename][$this->directionid][$linkid];
      } else {
	return false; // missing links in timetable table - insufficient data to make comparison
      }
    }
    return $timetable_time;
  }

  // Extracts the historical time taken to travel along all LinkIDs in $route_linkids starting at $start_time (both provided as parameters)
  private function calculate_historical_journeytime($route_linkids, $start_time) {
    $timetable_count = 0; // for testing how many links missing from historical data
    $historical_time = 0; // seconds journey expected to take (based on historical data)

    foreach($route_linkids as $linkid) {
      $relevant_historical_data = $this->get_relevant_historical_linktimes($start_time, $historical_time);

      if(array_key_exists($linkid, $relevant_historical_data)) {
        $historical_time += $relevant_historical_data[$linkid];
      } else {
        $historical_time += $this->timetable_data[$this->linename][$this->directionid][$linkid];
        $timetable_count++;
      }
    }

    if($timetable_count >= 5) {
      return false;
    } else {
      return $historical_time;
    }
  }

  // Function filters the full historical data and returns the data relevant
  // based on journey start time + elapsed journey time
  private function get_relevant_historical_linktimes($journey_start_time, $elapsed_journey_time) {
    $time = $journey_start_time + $elapsed_journey_time;

    $day = date('N',$time) % 7; // 0 -> 6
    $hour = date('G',$time); // 0 -> 23
    
    return $this->historical_data[$day][$hour];
  }

  // Extract the current time taken between the start stopid and end stopid
  private function calculate_current_journeytime($route_linkids) {
    $destination_linkid = $route_linkids[count($route_linkids) - 1];
    $source_linkid = $route_linkids[0];
    $journey_times = array();

    if(array_key_exists($destination_linkid, $this->current_data) && 
       array_key_exists($source_linkid, $this->current_data)) {
      $destination_uniqueids = $this->current_data[$destination_linkid];
      $source_uniqueids = $this->current_data[$source_linkid];

      foreach($destination_uniqueids as $uniqueid=>$linktime) {
        if(array_key_exists($uniqueid, $source_uniqueids)) {
          $journey_times[] = $this->sum_linktimes($uniqueid, $route_linkids);
        }
      }
      $this->limit_element_number($journey_times);
    } 

    if(count($journey_times) != 0) {
      return $this->calculate_median($journey_times);
    } else {
      return false;
    }
  }

  // Calculate the difference in journey time for the uniqueid journey provided as a parameter vs. historical time taken for journey taken at the same time start
  private function sum_linktimes($uniqueid, $route_linkids) {
    $current_time = 0;
    $start_time = $this->current_data[$route_linkids[0]][$uniqueid][0]; // arrival_time at origin stop

    $historical_journey_time = $this->calculate_historical_journeytime($route_linkids, $start_time); // for comparison

    foreach($route_linkids as $linkid) {
      if(array_key_exists($linkid, $this->current_data) && 
         array_key_exists($uniqueid, $this->current_data[$linkid])) {
        $current_time += $this->current_data[$linkid][$uniqueid][1];
      } else {
        $relevant_historical_data = $this->get_relevant_historical_linktimes($start_time, $current_time); // for filling in gaps in current data...
        if(array_key_exists($linkid, $relevant_historical_data)) {
	  $current_time += $relevant_historical_data[$linkid];
	} else {
	  $current_time += $this->timetable_data[$this->linename][$this->directionid][$linkid];
	}
      }
    }

    return $current_time - $historical_journey_time;
  }


  // Only calculate median of most recent journeys (class variable)
  private function limit_element_number(&$array) {
    if(count($array) > $this->buses_in_median) {
      $array = array_slice($array, 0, $this->buses_in_median);
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