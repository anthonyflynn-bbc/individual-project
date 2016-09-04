<?php

// Initialise cURL session
$curl = curl_init();

// Set cURL options:
curl_setopt($curl, CURLOPT_URL,
"http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?LineID=3,133&ReturnList=Stoppointname,StopID,VisitNumber,LineID,DestinationText,VehicleID,TripID,RegistrationNumber,EstimatedTime,ExpireTime");

curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);

curl_setopt($curl, CURLOPT_USERPWD, "username:password"); // user data removed

curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);


// Execute the cURL session
$result = curl_exec($curl);
//print $result;

// Open the file containing the JSON
$handle = fopen("test_json", 'r'); // filename redacted

$line = fgets($handle); // Get a line from the file
$trimmed = trim($line, "[]\n\r"); // Remove leading and trailing array brackets
$pieces = explode(",",$trimmed); // Split string contents into array

while($line) {
  if(count($pieces) == 11 && $pieces[0] == 1) {
    foreach($pieces as $value) {
      echo $value;
      echo "\n";
    }
  }

  $line = fgets($handle);
  $trimmed = trim($line, "[]\n\r");
  $pieces = explode(",",$trimmed);
}

?>
