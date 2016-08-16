<?php

include_once('/data/individual_project/php/validation/PredictionsClass.php');

$save_filename = date('Ymd-Hi',time());

$p = new Predictions($save_filename);
$p->get_data();






?>