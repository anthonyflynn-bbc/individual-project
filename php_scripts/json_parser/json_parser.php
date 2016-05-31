<?php

$handle = fopen("test_json", 'r'); // filename redacted

$line = fgets($handle);
$trimmed = trim($line, "[]\n\r"); //Remove leading and trailing array brackets
$pieces = explode(",",$trimmed);

while($line) {
  if(count($pieces) == 11 && $pieces[0] == 1) {
    $pieces[1] = substr($pieces[1], 1, -1);
    $pieces[1] = "'".$pieces[1]."'";

    foreach($pieces as $value) {
      echo $value;
      echo "\n";
    }
  }

  $line = fgets($handle);
  $trimmed = trim($line, "[]\n\r");
  $pieces = explode(",",$trimmed);
}

?>


