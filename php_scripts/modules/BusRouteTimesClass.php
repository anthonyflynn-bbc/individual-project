<?php

class BusRouteTimes {
  protected $json_filename;
  protected $week_array; // array of 7 elements, representing link times for each day

  // Constructor
  function __construct($linename) {
    $this->json_filename = "../data/".$linename.".json";
    $this->week_array = array_fill(0, 7, array());
  }

  // Function processes the link time data between stops (included in the array parameter 
  // $link_times_array), for the day provided as a parameter
  function process_one_day($link_times_array, $day) {
    $day_array = array_fill(0, 24, array());

    foreach($link_times_array as $entry) {
      $hour = $entry['hour']; // check what hour the entry is for
      $linkid = $entry['start'].$entry['end']; // extract the linkid
      $day_array[$hour][$linkid] = $entry['link_time']; // use linkid as key
    }

    unset($this->week_array[$day]); // delete the old data stored
    $this->week_array[$day] = $day_array;    
  }

  // Function saves the processed data, encoded into JSON format into a file
  // $json_filename
  function save_json() {
    $json_string = json_encode($this->week_array);

    $fp = fopen($this->json_filename, "w+");
    fwrite($fp, $json_string);
    fclose($fp);
  }
}

?>