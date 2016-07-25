<?php

include ('LiveArrivalsClass_redo.php');

$process = new LiveArrivals(5*60);
$process->update_data();

?>