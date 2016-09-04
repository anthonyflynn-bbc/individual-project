<?php

include '/data/individual_project/php/modules/BatchHourlyClass.php';

// period of which to extract batch_journey data
$backup_start_time = '2016-07-06 00:00:00'; 
$backup_end_time = '2016-07-07 12:00:00'; 

$start_time_unix = strtotime($backup_start_time);
$end_time_unix = strtotime($backup_end_time);

while($start_time_unix < $end_time_unix) {
  $batch_end = $start_time_unix + 60 * 60;
  $midnight_previous_day = strtotime('yesterday',$start_time_unix);

  $batch_job = new BatchHourly($start_time_unix, $batch_end, $midnight_previous_day);
  $batch_job->complete_batch();
  $start_time_unix += 60 * 60;
  echo "Batch written\n";
}

?>
