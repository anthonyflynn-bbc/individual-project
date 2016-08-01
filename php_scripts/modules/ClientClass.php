<?php

// ClientClass.php
// Anthony Miles Flynn
// (1/8/16)
// Client class for extracting historic journey times and current journey times

//345678901234567890123456789012345678901234567890123456789012345678901234567890
class Client {
  private $linename;
  private $directionid;
  private $start_stopid;
  private $historical_data;
  private $current_data;

  // Constructor
  public function __construct($linename, $directionid, $start_stopid) {
    $this->linename = $linename;
    $this->directionid = $directionid;
    $this->start_stopid = $start_stopid;
    $this->historical_data = $this->load_parse_json("tfl.doc.ic.ac.uk/historic/"
						   .$linename.".json");
    $this->current_data = $this->load_parse_json("tfl.doc.ic.ac.uk/current/"
						   .$this->linename.".json");
    $this->route_sequence = $this->load_parse_json("tfl.doc.ic.ac.uk/routes/"
						   ."route_reference.json");
  }

  // called by client
  public function get_journey_times() {
    $ordered_stopids = $this->route_sequence[$this->linename][$this->directionid];
    $relevant_historical_data = $this->get_relevant_historical_linktimes();

    $start_position = array_search($this->start_stopid, $ordered_stopids); // returns array position of start stopid
    $journey_times = array(); // associative array: key = stopid, value = time until arrival
    $journey_times[$this->start_stopid] = 0;

    for($i = $start_position + 1; $i < count($ordered_stopids); $i++) {
      $destination_stopid = $ordered_stopids[$i];
      $time = array(); // 2 elements, element 0 contains historic time, element 1 contains live time
      $route_linkids = $this->load_linkid_sequence($this->start_stopid, $destination_stopid);
      $time[0] = $this->calculate_historical_journeytime($route_linkids); // historic time
      $time[1] = $this->calculate_current_journeytime($route_linkids); // live time

      $previous_stopid = $ordered_stopids[$i - 1];
      if($time[1] < $journey_times[$previous_stopid][1]) {
        $time[1] = $journey_times[$previous_stopid][1] + $relevant_historical_data[$previous_stopid.$destination_stopid];
      }
      $journey_times[$destination_stopid] = $time;
    }
    print_r($journey_times);

    foreach($journey_times as $v) {
      echo $v[0]."\n";
    }

    foreach($journey_times as $v) {
      echo $v[1]."\n";
    }
  }

  // Function filters the full historical data and returns the data relevant
  // to the current day and time
  private function get_relevant_historical_linktimes() {
    $current_day = date('N',time()) % 7; // 0 -> 6
    $current_hour = date('G',time()); // 0 -> 23
    
    return $this->historical_data[$current_day][$current_hour];
  }

  // DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  private function limit_element_number(&$array, $number_elements) {
    if(count($array) > 5) {
      $array = array_slice($array, 0, $number_elements);
    }
  }


  // DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  private function calculate_median($journey_times) {
    rsort($journey_times); // sorts array in reverse order
    $middle = round(count($journey_times) / 2); 
    return $journey_times[$middle-1]; 
  } //  ADD SOME FUNCTIONALITY TO CHECK IF THERE ARE ANY ELEMENTS IN THE ARRAY - OTHERWISE RAISE AN ERROR OR SAY NO PREDICTION DATA CURRENTLY AVAILABLE

  // DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  private function calculate_historical_journeytime($route_linkids) {
    $relevant_historical_data = $this->get_relevant_historical_linktimes();
    $historical_time = 0;

    foreach($route_linkids as $linkid) {
      $historical_time += $relevant_historical_data[$linkid];
    }
    return $historical_time;
  }

  // DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  private function calculate_current_journeytime($route_linkids) {
    $relevant_historical_data = $this->get_relevant_historical_linktimes();
    $destination_linkid = $route_linkids[count($route_linkids) - 1];
    $source_linkid = $route_linkids[0];

    $destination_uniqueids = $this->current_data[$destination_linkid];
    $source_uniqueids = $this->current_data[$source_linkid];

    $journey_times = array();

    foreach($destination_uniqueids as $uniqueid=>$linktime) {
      if(array_key_exists($uniqueid, $source_uniqueids)) {
        $journey_times[] = $this->sum_linktimes($uniqueid, $route_linkids, $relevant_historical_data);
      }
    }

    $this->limit_element_number($journey_times, 5);

    if(count($journey_times) == 0) {
      return $this->calculate_historical_journeytime($route_linkids);
    } else {    
      return $this->calculate_median($journey_times);
    }
  }

  // DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  private function sum_linktimes($uniqueid, $route_linkids, $relevant_historical_data) {
    $total_time = 0;
    $historic_data_count = 0;
    foreach($route_linkids as $linkid) {
      if(array_key_exists($uniqueid, $this->current_data[$linkid])) {
        $total_time += $this->current_data[$linkid][$uniqueid];
      } else {
        $total_time += $relevant_historical_data[$linkid];
	$historic_data_count++;
      }
    }
    echo $uniqueid.": ".$historic_data_count." out of ".count($route_linkids)." based on historic data\n";
    return $total_time;
  }


  // DESCRIPTION !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  private function load_linkid_sequence($start_stopid, $end_stopid) {
    $ordered_stopids = $this->route_sequence[$this->linename][$this->directionid];

    $start_position = array_search($start_stopid, $ordered_stopids);
    $end_position = array_search($end_stopid, $ordered_stopids);

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

  // Function downloads the data at $url (provided as a paramaeter) and parses json
  private function load_parse_json($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // curl_exec returns data as string

    $contents = curl_exec($curl);
    return json_decode($contents, true);
  }
}

?>