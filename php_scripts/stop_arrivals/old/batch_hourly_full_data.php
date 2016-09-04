<?php

include 'BatchHourlyClass.php';

$current_time = time(); // record time of start of program execution
$start_time = $current_time  - 6 * 60 * 60; // 6 hours prior to current time
$end_time = $current_time  - 5 * 60 * 60; // 5 hours prior to current time

$batch_job = new BatchHourly($start_time, $end_time);
$batch_job->complete_batch();


?>