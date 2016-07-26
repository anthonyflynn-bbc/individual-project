<?php

include ('/data/individual_project/php/modules/LiveArrivalsClass.php');

$process = new LiveArrivals(5*60);
$process->update_data();

?>