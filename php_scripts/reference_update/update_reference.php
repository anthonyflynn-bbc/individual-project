<?php

// update_reference.php
// Anthony Miles Flynn
// (8/9/16)
// Process for running StopReferenceUpdate class (to determine if stop reference
// data hase been updated).  If so, the stop reference and route reference
// relations are updated and new route JSON output files are created.

include_once ('/data/individual_project/php/modules/StopReferenceUpdateClass.php');
include_once ('/data/individual_project/php/modules/RouteReferenceClass.php');
include_once ('/data/individual_project/php/modules/RouteReferenceJSONUpdateClass.php');

// Check if any update to baseversion, and if so, update stop_reference
$stop_reference_process = new StopReferenceUpdate();

// If stop reference updated, then update the route reference relation and
// route reference JSON output files
$route_reference_update = new RouteReference();
$route_reference_update->update_data();
$route_json_update = new RouteReferenceJSONUpdate();
$route_json_update->complete_updates();    

?>