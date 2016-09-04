<?php

// check_live_arrivals_process.php
// Anthony Miles Flynn
// (4/9/16)
// Script for determining whether the live arrivals process is running

// EXTRACT THE PROCESS ID OF THE RELEVANT SCRIPT ///////////////////////////////

$PID=-1;
$ps_command = "ps aux | grep [l]ive_arrivals_process_5min.php"; // set up command
$file_location = "/data/individual_project/php/live_arrivals/"
	       	."live_arrivals_process_5min.php";

$ps_result = parse_shell_result($ps_command);

foreach($ps_result as $line) {
  // '/[ ]+/' means: / = start and end of pattern, then the characters of 
  // interest in [], + means one or more of preceeding character
  $line = preg_split('/[ ]+/',$line);

  if(count($line) == 12 && $line[11] == $file_location) {
    $PID = $line[1];
  }
}

if($PID == -1) { // process does not exist
  echo "process doesn't exist";
  exit(1);
}

function parse_shell_result($command) {
  $result = shell_exec($command); // execute the command
  return explode("\n",$result);
}

?>

