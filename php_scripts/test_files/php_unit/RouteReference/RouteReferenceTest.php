<?php

// RouteReferenceTest.php
// Anthony Miles Flynn
// (08/09/16)
// PHPUnit test file for RouteReference class

use phpunit\framework\TestCase;
require_once('/data/individual_project/php/modules/RouteReferenceClass.php');
require_once('RouteReferenceFunctions.php'); // tests of particular funcitons

class RouteReferenceTest extends TestCase {
  // Tests direction_from_id
  public function test_direction_id() {
    $functions = new RouteReferenceFunctions("phpunit_route_reference");
    $this->assertEquals("outbound", $functions->direction_from_id(1));
    $this->assertEquals("inbound", $functions->direction_from_id(0));
    $this->assertEquals("inbound", $functions->direction_from_id(2));
  }

  // Tests correct functionality of get_stops method
  public function test_get_stops() {
    $functions = new RouteReferenceFunctions("phpunit_route_reference");
    $functions->get_stops('3', 1, $results_array);
    $this->assertcount(47, $results_array);

    // Test first element:
    $this->assertEquals('3', $results_array[0]['linename']);
    $this->assertEquals(1, $results_array[0]['directionid']);
    $this->assertEquals('490005537AP', $results_array[0]['stopcode2']);
    $this->assertEquals(0, $results_array[0]['stopnumber']);

    // Test last element:
    $this->assertEquals('3', $results_array[46]['linename']);
    $this->assertEquals(1, $results_array[46]['directionid']);
    $this->assertEquals('490005869S3', $results_array[46]['stopcode2']);
    $this->assertEquals(46, $results_array[46]['stopnumber']);
  }

  // Checks that data being correctly inserted into route_reference and
  // correctly deleted at the end
  public function test_insert_route_reference() {
    $db = new Database();
    $this->clear_phpunit_route_reference($db);
    $functions = new RouteReferenceFunctions("phpunit_route_reference");
    $functions->get_stops('159', 2, $results_array);
    $functions->insert_route_reference($results_array);

    $sql = "SELECT * FROM phpunit_route_reference ORDER BY stopnumber";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(42, $result);
    $this->assertEquals(0, $result[0]['stopnumber']);
    $this->assertEquals('159', $result[0]['linename']);
    $this->assertEquals('2', $result[0]['directionid']);
    $this->assertEquals('490000144L', $result[0]['stopcode2']);

    // check data deleting using class function
    $functions->delete_database_contents();
    $sql = "SELECT * FROM phpunit_route_reference";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(0, $result);
  }

  // Tests that function returns all data from URL in an array (with correct 
  // contents) - both functions get_all_linenames() and download_json() used
  public function test_download_json_all_linenames() {
    $url = "https://api.tfl.gov.uk/Line/Mode/bus";
    $functions = new RouteReferenceFunctions("phpunit_route_reference");
    $data = $functions->download_json($url);
    $results = $functions->get_all_linenames($data);
    $this->assertCount(658, $results);
  }

  // Function deletes any existing information in the route reference table
  private function clear_phpunit_route_reference($db) {
    $sql = "DELETE FROM phpunit_route_reference";
    $db->execute_sql($sql);
  }

}
