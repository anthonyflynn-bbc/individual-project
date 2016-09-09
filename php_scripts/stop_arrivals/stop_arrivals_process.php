<?php

// stop_arrivals_process.php
// Anthony Miles Flynn
// (8/9/16)
// Process for instantiating the StopArrivals class extracting stop arrivals
// from stop prediction data.  Run each hour on data 5-6 hours in the past.

include_once ('/data/individual_project/php/modules/StopArrivalsClass.php');

$current_time = time(); // record time of start of program execution
$start_time = $current_time  - 6 * 60 * 60; // 6 hours prior to current time
$end_time = $current_time  - 5 * 60 * 60; // 5 hours prior to current time

$stop_arrivals_extraction = new StopArrivals($start_time, $end_time);
$stop_arrivals_extraction->complete_batch();

?>