<?php

include ('LinkTimeCalculatorClass.php');

$process = new LinkTimeCalculator(time());
$process->complete_update();

?>