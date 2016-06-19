<?php

// CHECK IF BASEVERSION IS UP TO DATE /////////////////////////////////

// Gets the baseversion of the most recently saved stop_reference data:
$version_file = "/data/individual_project/php/stop_reference/"
  		   ."version/version.txt";
$previous_version = file_get_contents($version_file);
$previous_version = trim($previous_version,"\n");

// Get the current version of baseversion from the TfL feed:
$version_url = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
	      ."ReturnList=BaseVersion";

$version_data = file_get_contents($version_url);
$version_array = explode("\n", $version_data); // array of URA & Baseversion
$current_version;

for ($i=0; $i < count($version_array); $i++) {
  //remove characters from front and back ('[',']' and newline character)
  $trimmed = trim($version_array[$i], "[]\n\r"); 

  $entry = str_getcsv($trimmed); //parses the CSV string into an array

  // To be of interest, the line must start with a '3' (Baseversion array)
  if($entry[0] == 3) {
    $current_version = $entry[1];
    if(!out_of_date()) {
      exit(0); // Data up to date - exit script          
    }
    echo "Updating data to version ".$entry[1]."\n";
  }  
}

function out_of_date() {
  if($GLOBALS['previous_version'] == $GLOBALS['current_version']) {
    return false;
  } else {
    return true;
  }
}

// UPDATE STOP_REFERENCE DATA IF BASEVERSION OUT OF DATE //////////////

// SET UP DATABASE CONNECTION /////////////////////////////////////////

// Database access information:
$database_type = 'pgsql';
$server_ip = "";
$database_name = "bus_data";
$username = "";
$password = "";

// Create database connection:
try {
  $DBH = new PDO("$database_type:host=$server_ip;dbname=$database_name",
		  $username,$password);
}
catch(PDOException $e) {
  echo $e->getMessage()."\n";
  exit(1);
}

// SET UP STREAM WRAPPER //////////////////////////////////////////////

class tflStreamWrapper {
  protected $buff; // buffer to store any partial lines during parsing
  
  // function called immediately after wrapper initialised
  function stream_open($path, $mode, $options, &$opened_path) {
    return true;
  }

  // function called whenever a write operation is called by cURL
  function stream_write($data) {
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

    $insert = "INSERT INTO stop_reference (stoppointname,stopid,stopcode1,"
    	     ."stopcode2,stoppointtype,towards,bearing,stoppointindicator,"
	     ."stoppointstate,latitude,longitude) ";

    // For each stop_data item in turn:
    for ($i=0; $i < count($stop_data); $i++) {
      //remove characters from front and back ('[',']' and newline character)
      $trimmed = trim($stop_data[$i], "[]\n\r"); 

      $entry = str_getcsv($trimmed); //parses the CSV string into an array

      // To be of interest, the line must have 12 pieces of data, and should 
      // exclude the URA Version array (must start with a '0')
      if(count($entry) == 12 && $entry[0] == 0) {
          //place quotes around strings / escape special characters
	  $entry[1] = $GLOBALS['DBH']->quote($entry[1]); //stoppointname
	  $entry[2] = $GLOBALS['DBH']->quote($entry[2]); //stopid
	  $entry[3] = $GLOBALS['DBH']->quote($entry[3]); //stopcode1
	  $entry[4] = $GLOBALS['DBH']->quote($entry[4]); //stopcode2
	  $entry[5] = $GLOBALS['DBH']->quote($entry[5]); //stopointtype
	  $entry[6] = $GLOBALS['DBH']->quote($entry[6]); //towards
	  $entry[8] = $GLOBALS['DBH']->quote($entry[8]); //stoppointindicator

	  // Set up string to insert values
          $insert_entry = $insert."values ($entry[1],$entry[2],$entry[3],"
	  		  	 ."$entry[4],$entry[5],$entry[6],$entry[7],"
				 ."$entry[8],$entry[9],$entry[10],$entry[11])";

	  try {
 	    $statement_obj = $GLOBALS['DBH']->prepare($insert_entry);
	    $statement_obj->execute();
	  }
	  catch(PDOException $e) {
	    echo $e -> getMessage()."\n";
	  }
      }
    }
    return strlen($data);
  }
}

// Register wrapper:
stream_wrapper_register("tflStreamWrapper","tflStreamWrapper") 
  or die("Failed to register protocol");

// Open a file handler using the wrapper protocol
$fp = fopen("tflStreamWrapper://tflStream","r+")
  or die("Error opening wrapper file handler");

// Delete any existing information in database:
$delete = "DELETE FROM stop_reference"; 
try {
  $delete_obj = $GLOBALS['DBH']->prepare($delete);
  $delete_obj->execute();
}
catch(PDOException $e) {
  echo $e -> getMessage()."\n";
}


// SET UP cURL SESSION ////////////////////////////////////////////////

// Initialise cURL session:
$curl = curl_init(); 

// Set cURL options:
curl_setopt($curl, CURLOPT_URL, 
	    "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
	   ."StopAlso=true&ReturnList=StopPointName,StopID,StopCode1,StopCode2,"
	   ."StopPointType,Towards,Bearing,StopPointIndicator,StopPointState,"
	   ."Latitude,Longitude");

curl_setopt($curl, CURLOPT_FILE, $fp); // file pointer for output data

// Start the cURL session (begin collecting data):
curl_exec($curl);

// Close the connection and file handler when finished:
curl_close($curl);
fclose($fp);


// Update the version.txt file ////////////////////////////////////////

$version_handle = fopen($version_file,"w");

if($version_handle) {
  $previous_version = fwrite($version_handle, $current_version);
} else {
  echo "Error writing to version.txt file";
  exit(1);
}

if(!fclose($version_handle)) {
  echo "Error closing version.txt file";
}

echo "Update complete\n";

?>
