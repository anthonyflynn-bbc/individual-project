<?php

include_once('/data/individual_project/php/validation/CheckPredictionsClass.php');

$save_filename = date('Ymd-Hi',time()-7*3600); // checked 7 hours after (so arrivals processed)
//$save_filename = "20160817-1600";

$check_predictions = new CheckPredictions($save_filename, $save_filename);
$check_predictions->check_predictions();



?>