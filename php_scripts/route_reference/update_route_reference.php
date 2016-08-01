<?php

include ('/data/individual_project/php/modules/RouteReferenceClass.php');
include ('/data/individual_project/php/modules/RouteReferenceJSONUpdateClass.php');

$route_reference_update = new RouteReference();
$route_reference_update->update_data();
$route_json_update = new RouteReferenceJSONUpdate();
$route_json_update->complete_update();

?>