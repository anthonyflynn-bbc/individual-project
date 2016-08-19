<?php

include_once('/data/individual_project/php/validation/GetPredictionsClass.php');

$save_filename = date('Ymd-Hi',time());

$p = new Predictions($save_filename, 5); // use 5 most recent buses for median
$p->get_data();






?>