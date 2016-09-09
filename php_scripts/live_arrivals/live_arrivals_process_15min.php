<?php

// Script to start the live arrivals	process, using a wait time of 15 minutes
// and retaining the arrival data for 1	hour and link times data for 2 hours.
// Data saved in the "/wait15" directory.

include_once ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(15*60, 60*60, "wait15/");
$process->update_data();

?>