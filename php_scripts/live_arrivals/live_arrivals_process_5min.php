<?php

// Script to start the live arrivals process, using a wait time of 5 minutes
// and retaining the arrival data for 1 hour and link times data for 2 hours.
// Data saved in the "/wait5" directory.

include_once ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(5*60, 60*60, "wait5/");
$process->update_data();

?>