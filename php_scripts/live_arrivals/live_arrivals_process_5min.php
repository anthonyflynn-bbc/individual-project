<?php

include ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(5*60, 60*60, "wait5/");
$process->update_data();

?>