<?php

include ('/data/individual_project/php/modules/LinkTimeCalculatorClass_date.php');
include ('/data/individual_project/php/modules/RemoveDuplicatesClass.php');
include ('/data/individual_project/php/modules/LinkTimeDayAverageClass.php');

$backup_time = time();

$remove_duplicates_process = new RemoveDuplicates($backup_time);
$remove_duplicates_process->process_data();
$linktime_process = new LinkTimeCalculator($backup_time);
$linktime_process->complete_update();
$average_linktime_process = new LinkTimeDayAverage($backup_time);
$average_linktime_process->complete_update();



?>