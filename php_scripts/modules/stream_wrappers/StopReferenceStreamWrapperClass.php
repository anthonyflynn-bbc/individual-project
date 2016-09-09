<?php

// StopReferenceStreamWrapperClass.php
// Anthony Miles Flynn
// (30/07/16)
// Program works together with StopReferenceUpdateClass.php to update the stop
// reference table.  The stream_write function takes in a string of data receive
// from the TfL API, formats it correctly for insertion into the stop reference
// table and inserts it in batches (as received).

class StopReferenceWrapper {
  private $buff; // buffer to store any partial lines during parsing
  private $database;
  private $DBH;
  private $temporary_database;
  private $permanent_database;
  private $stop_reference_schema;

  // function called immediately after wrapper initialised
  public function stream_open($path, $mode, $options, &$opened_path) {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->temporary_database = "stop_reference_temp";
    $this->permanent_database = "stop_reference";
    $this->stop_reference_schema = 
    	"(stoppointname,stopid,stopcode1,stopcode2,stoppointtype,towards,bearing,"
       ."stoppointindicator,stoppointstate,latitude,longitude) ";
    return true;
  }

  // function called whenever a write operation is called by cURL
  public function stream_write($data) {
    $stop_data = explode("\n", $data); //creates array of stop data from stream
    
    // $buff contains the incomplete last line of data that was present when
    // the last batch of data was written to the database (or nothing if there
    // were no incomplete lines).  This is therefore prefixed to the first item
    // in the $stop_data array
    $stop_data[0] = $this->buff.$stop_data[0];
 
    // Save the last line of this batch of data to the buffer (in case it is
    // incomplete.  Will be prefixed to first item in next batch
    $stop_data_count = count($stop_data);
    $this->buff = $stop_data[$stop_data_count - 1];
    unset($stop_data[$stop_data_count - 1]); //delete last item in $stop_data

    $insert = "INSERT INTO ".$this->temporary_database." ".$this->stop_reference_schema;

    // For each stop_data item in turn:
    for ($i=0; $i < count($stop_data); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($stop_data[$i], "[]\n\r"); 

      $entry = str_getcsv($trimmed); //parses the CSV string into an array

      // To be of interest, the line must have 12 pieces of data, and should 
      // exclude the URA Version array (must start with a '0')
      if(count($entry) == 12 && $entry[0] == 0) {
          //place quotes around strings / escape special characters
	  $entry[1] = $this->DBH->quote($entry[1]); //stoppointname
	  $entry[2] = $this->DBH->quote($entry[2]); //stopid
	  $entry[3] = $this->DBH->quote($entry[3]); //stopcode1
	  $entry[4] = $this->DBH->quote($entry[4]); //stopcode2
	  $entry[5] = $this->DBH->quote($entry[5]); //stopointtype
	  $entry[6] = $this->DBH->quote($entry[6]); //towards
	  $entry[8] = $this->DBH->quote($entry[8]); //stoppointindicator

	  // Set up string to insert values
          $insert_entry = $insert."values ($entry[1],$entry[2],$entry[3],"
	  		  	 ."$entry[4],$entry[5],$entry[6],$entry[7],"
				 ."$entry[8],$entry[9],$entry[10],$entry[11])";

	  $this->database->execute_sql($insert_entry);
      }
    }
    return strlen($data);
  }
}

// Register wrapper:
stream_wrapper_register("StopReferenceWrapper","StopReferenceWrapper") 
  or die("Failed to register protocol");

?>