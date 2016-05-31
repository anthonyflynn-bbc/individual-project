<?php

$servername = ""; // data removed
$databasename = "bus_data";
$username = ""; // data removed
$password = ""; // data removed

// Create connection:
$connection = pg_connect("host=$servername dbname=$databasename user=$username password=$password");

// Check connection - exit if not connected
if (!$connection) {
  die("An error occurred.\n");
}

// Create function to output data returned from database query
function outputData($r) {
  while ($row = pg_fetch_row($r)) {
    foreach($row as $value) {
      echo "$value" . "\t";
    }
    echo "\n";
  }
}

// Query database get get all data
$result = pg_query($connection, "SELECT * FROM sample_data2");
if (!$result) {
  die("An error occurred.\n");
}

// Output data
outputData($result);

// Insert new values into the database
$new_entry = pg_query($connection, "INSERT INTO sample_data2(stoppointname,linename,destinationtext,vehicleid,estimatedtime,expirytime)
	   VALUES('Test location','123','Destination',6547, 1234567898765,9876543212345);");


// Query database again to get updated data
$result2 = pg_query($connection, "SELECT * FROM sample_data2");
if (!$result2) {
  die("An error occurred.\n");
}

// Output data
outputData($result2);
 
?>
