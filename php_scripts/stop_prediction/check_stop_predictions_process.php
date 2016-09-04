<?php

// check_stop_predictions_process.php
// Anthony Miles Flynn
// (4/9/16)
// Script for determining whether the stop predictions process is running
// and whether data is still being received from the TfL servers

// EXTRACT THE PROCESS ID OF THE RELEVANT SCRIPT ///////////////////////////////

$PID=-1;
$ps_command = "ps aux | grep [s]top_predictions_process.php"; // set up command
$file_location = "/data/individual_project/php/stop_predictions/"
	        ."stop_predictions_process.php";

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
  exit(2);
}

// EXTRACT THE PORT NUMBER FOR THE RELEVANT PROCESS ID /////////////////////////

$tfl_ip = gethostbyname('countdown.api.tfl.gov.uk');
$port;
$netstat_command = "netstat -tlpan | grep $PID"; // set up command
$netstat_result = parse_shell_result($netstat_command);

foreach($netstat_result as $line) {
  $line = preg_split('/[ ]+/',$line);

  if(count($line) == 8) {
    $dest_ip_port = explode(":",$line[4]);
    if($dest_ip_port[0] == $tfl_ip) {
      $source_ip_port = explode(":",$line[3]);
      $port = $source_ip_port[1];
    }
  }
}

// CHECK IF PACKETS ARE STILL BEING TRANSFERRED ////////////////////////////////

// tcpdump command for the relevant ip / port.  Collects data for 62 seconds 
// (whitespace message sent out once per minute at a minimum)
$tcp_command = "timeout 62s tcpdump -n \"src host $tfl_ip and dst port $port\"";

$descriptors = array(
  1 => array('pipe', 'w'), // stdout
  2 => array('pipe', 'w')  // stderr
  );

// Start the process.  Pipes array collects data written to stdout & stderr
$process = proc_open($tcp_command, $descriptors, $pipes);
  
// Check process executed successfully
if(!is_resource($process)) {
  throw new Exception("Process could not be executed");
}
  
// Read contents of stdout and stderr
$stdout_buffer = stream_get_contents($pipes[1]);
$stderr_buffer = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

check_packet_transmission($stdout_buffer);


// HELPER FUNCTIONS ////////////////////////////////////////////////////////////

function check_packet_transmission(&$buffer) {
  $vm_ip_port = "146.169.47.42.".$GLOBALS['port'].":";
  $tfl_ip_port = $GLOBALS['tfl_ip'].".80";

  $rows = explode("\n",$buffer);
  for($i = 0; $i < count($rows); $i++) {
    $row_detail = explode(" ", $rows[$i]);
    if(count($row_detail) == 15 && $row_detail[2] == $tfl_ip_port 
       && $row_detail[4] == $vm_ip_port && $row_detail[14] > 0) {
      exit(0); // data being transferred
    }
  }
  $kill_command = "kill ".$GLOBALS['PID'];
  shell_exec($kill_command);
  exit(1); // data not being transferred
}

function parse_shell_result($command) {
  $result = shell_exec($command); // execute the command
  return explode("\n",$result);
}

?>

