<?php

// StopReferenceUpdateFunctions.php
// Anthony Miles Flynn
// (29/07/16)
// Public versions of StopReferenceUpdateClass - used for unit testing

include_once '/data/individual_project/php/test_files/php_unit/StopReference/'
	    .'StopReferenceStreamWrapperClassTest.php';
include_once '/data/individual_project/php/modules/DatabaseClass.php';
include_once '/data/individual_project/php/modules/HttpClientClass.php';

class StopReferenceFunctions {
  public $previous_version;
  public $baseversion_array;
  public $current_version;
  private $database;
  private $DBH; 
  public $fp;
  private $temporary_database; // used to hold new data before transferred to old
  private $permanent_database; // database used by application
  private $stop_reference_schema;
  private $version_file;

  // Constructor - checks whether the data requires updating
  public function __construct($temporary_database = "stop_reference_temp",
			      $permanent_database = "stop_reference",
			      $version_file = "/data/individual_project/php/"
			      		     ."stop_reference/version.txt") {
    $this->version_file = $version_file;
    $this->previous_version = $this->get_previous_version();
    $this->baseversion_array = $this->get_baseversion_array();
    $this->current_version = $this->get_current_version();

    if($this->update_required() === false) {
      exit(0); // exits script if stop data up to date, otherwise continues
    }

    // Only carried out if there is a newer version of stop reference data available:
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();

    $this->temporary_database = $temporary_database;
    $this->permanent_database = $permanent_database;
    $this->stop_reference_schema = 
    	"(stoppointname,stopid,stopcode1,stopcode2,stoppointtype,towards,bearing,"
       ."stoppointindicator,stoppointstate,latitude,longitude) ";
    $this->fp = fopen("StopReferenceWrapper://StopStream","r+")
      or die("Error opening wrapper file handler"); 
  }

  // Function extracts the baseversion stamp reflecting the version of the data
  // contained in stop_reference
  public function get_previous_version() {
    return trim(file_get_contents($this->version_file),"\n");
  }

  // Function gets the current version of baseversion from the TfL feed
  public function get_baseversion_array() {
    $version_url = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
	          ."ReturnList=BaseVersion";

    $version_data = file_get_contents($version_url);
    return explode("\n", $version_data); // array of URA & Baseversion
  }

  // Function gets the current version of the data based on the baseversion
  // array downloaded from the TfL feed
  public function get_current_version() {  
    for ($i=0; $i < count($this->baseversion_array); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($this->baseversion_array[$i], "[]\n\r"); 

      $entry = str_getcsv($trimmed); //parses the CSV string into an array

      // To be of interest, the line must start with a '3' (Baseversion array)
      if($entry[0] == 3) {
        return $entry[1];
      }
    }
    return -1;
  }

  // Function checks whether the version data contained in stop_reference is
  // up to date.  If update required, returns true; otherwise false
  public function update_required() {
    if($this->previous_version == $this->current_version) {
      return false; // Data up to date - exit script          
    }
    echo "Updating data to version ".$this->current_version."\n";
    return true;
  }

  // Function updates the stop reference database table to the latest version
  public function update_data() {
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
	   ."StopAlso=true&ReturnList=StopPointName,StopID,StopCode1,StopCode2,"
	   ."StopPointType,Towards,Bearing,StopPointIndicator,StopPointState,"
	   ."Latitude,Longitude";

    $Http = new HttpClient($url, $this->fp);
    $Http->start_data_collection();
    $this->make_database_updates(); 
    $this->save_latest_version(); // update version.txt file

    // Delete any information in temp database:
    $delete = "DELETE FROM $this->temporary_database"; 
    $this->database->execute_sql($delete);

    echo "Update complete\n";

    $Http->close_connection();
    fclose($this->fp);
    exit(1);
  }

  // Function compares the permanent and temporary versions of the stop 
  // reference database and updates the permanent version to reflect changes
  public function make_database_updates() {
    // Fetch additions and removals in new database:
    $additions = $this->get_difference($this->temporary_database, $this->permanent_database);
    $removals = $this->get_difference($this->permanent_database, $this->temporary_database);

    // Add additions to stop_reference database:
    foreach($additions as $new_stop) {
      $this->make_insertion($new_stop);
    }

    // Remove removals from stop_reference database:
    foreach($removals as $old_stop) {
      $this->make_removal($old_stop);
    }

    // Get and make alterations to stop_reference database:
    $alterations = $this->get_updates($this->temporary_database, $this->permanent_database);
    
    foreach($alterations as $changed_stop) {
      $this->make_update($changed_stop);
    }
  }

  // Function extracts the stop data for any stops where the stopid appears in
  // $database1 but does not in $database2
  public function get_difference($database1, $database2) {
    $sql = "SELECT * "
          ."FROM $database1 "
	  ."WHERE stopid IN "
	  ."(SELECT stopid "
	  ."FROM $database1 "
	  ."EXCEPT "
	  ."SELECT stopid "
	  ."FROM $database2)";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function inserts any new stops into the stop_reference table
  public function make_insertion($stop) {
    $sql = "INSERT INTO $this->permanent_database "
    	  .$this->stop_reference_schema
	  ."VALUES("
	  .$this->DBH->quote($stop['stoppointname']).","
	  .$this->DBH->quote($stop['stopid']).","
	  .$this->DBH->quote($stop['stopcode1']).","
	  .$this->DBH->quote($stop['stopcode2']).","
	  .$this->DBH->quote($stop['stoppointtype']).","
	  .$this->DBH->quote($stop['towards']).","
 	  .$stop['bearing'].","
	  .$this->DBH->quote($stop['stoppointindicator']).","
	  .$stop['stoppointstate'].","
	  .$stop['latitude'].","
	  .$stop['longitude'].")";

    $this->database->execute_sql($sql);
  }

  // Function removes any old stops from the stop_reference table
  public function make_removal($stop) {
    $sql = "DELETE FROM $this->permanent_database "
    	  ."WHERE stopid = ".$this->DBH->quote($stop['stopid']);

    $this->database->execute_sql($sql);
  }

  // Function extracts the stop data for any stops where there have been any
  // changes to the details related to that stop
  public function get_updates($database1, $database2) {
    $sql = "SELECT * "
    	  ."FROM $database1 "
	  ."EXCEPT "
	  ."SELECT * "
	  ."FROM $database2";

    return $this->database->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  // Function updates any stops in the stop_reference table where any details
  // have changed
  public function make_update($stop) {
    $this->DBH->beginTransaction();
    $this->make_removal($stop);
    $this->make_insertion($stop);
    $this->DBH->commit();
  }

  // Function saves the updated baseversion stamp of the data contained in
  // stop_reference to the file 'version.txt'
  public function save_latest_version() {
    $version_handle = fopen($this->version_file,"w");

    if($version_handle) {
      fwrite($version_handle, $this->current_version);
    } else {
      echo "Error writing to version.txt file";
    }

    if(!fclose($version_handle)) {
      echo "Error closing version.txt file";
    }
  }
}

?>