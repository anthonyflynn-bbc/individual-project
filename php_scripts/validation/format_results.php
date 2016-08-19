<?php

$results = json_decode(file_get_contents("/data/individual_project/php/validation/results/20160818-1300"));

foreach($results as $prediction) {
  if($prediction[2] != ""){
    echo $prediction[0]."\t".$prediction[1]."\t".$prediction[2]."\t".$prediction[3]."\t".$prediction[4]."\t".$prediction[5]."\t".$prediction[6]."\t".$prediction[7]."\t".$prediction[8]."\n";
  }
}




?>
