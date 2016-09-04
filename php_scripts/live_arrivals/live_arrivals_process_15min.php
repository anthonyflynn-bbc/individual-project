<?php

include ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(15*60, 60*60, "wait15/");
$process->update_data();

?>