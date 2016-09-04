<?php

include ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(10*60, 60*60, "wait10/");
$process->update_data();

?>