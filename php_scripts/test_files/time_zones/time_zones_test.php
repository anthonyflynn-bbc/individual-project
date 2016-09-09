<?php

// time_zones_test.php
// Anthony Miles Flynn
// (08/09/16)
// Files used for testing time zone issues around change to and from BST

include_once ('/data/individual_project/php/modules/DatabaseClass.php');

$db = new Database();
$DBH = $db->get_connection();

echo "30th October 12.59 GMT / 01.59 BST: ";
echo date('Y-m-d H:i:s',1477789140); //30th October 12.59 GMT / 01.59 BST
echo "\n30th October 01.01 GMT / 02.01 BST: ";
echo date('Y-m-d H:i:s',1477789260); //30th October 01.01 GMT / 02.01 BST
echo "\n30th October 01.59 GMT / 02.59 BST: ";
echo date('Y-m-d H:i:s',1477792740); //30th October 01.59 GMT / 02.59 BST
echo "\n30th October 02.01 GMT / 03.01 BST: ";
echo date('Y-m-d H:i:s',1477792860); //30th October 02.01 GMT / 03.01 BST

echo"\n";
$save_values = array();
$save_values[] = array('stopid'=>'123',
		      'visitnumber'=>1,
		      'destinationtext'=>'123',
		      'estimatedtime'=>($DBH->quote(date('Y-m-d H:i:s',1477789140))), //30th October 01.59 BST
		      'expiretime'=>($DBH->quote(date('Y-m-d H:i:s',1477789260))), //30th October 02.01 BST / 01.01 GMT
		      'recordtime'=>($DBH->quote(date('Y-m-d H:i:s',1477792740))), //30th October 01.59 GMT
		      'uniqueid'=>'20150101000001');

?>