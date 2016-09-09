<?php

// Script to start the live arrivals	process, using a wait time of 10 minutes
// and retaining the arrival data for 1	hour and link times data for 2 hours.
// Data saved in the "/wait10" directory.

include_once ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(10*60, 60*60, "wait10/");
$process->update_data();

?>