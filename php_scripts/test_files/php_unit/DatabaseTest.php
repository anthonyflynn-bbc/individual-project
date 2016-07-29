<?php

use phpunit\framework\TestCase;
require_once('/data/individual_project/php/modules/DatabaseClass.php');

class DatabaseTest extends TestCase {
  // Tests function execute_sql($sql_statement)
  public function test_execute_sql() {
    $db = new Database();
    $sql = "SELECT * FROM phpunittest WHERE recordtime = '2016-07-27 00:00:26'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals('3077', $result[0]['stopid']);
    $this->assertEquals('20160725108720', $result[0]['uniqueid']);
    $this->assertNotEquals('1234', $result[0]['stopid']);
    $this->assertCount(8, $result[0]); // 8 columns, 1 row
    $this->assertArrayHasKey('stopid', $result[0]);
    $this->assertArrayHasKey('visitnumber', $result[0]);
    $this->assertArrayHasKey('destinationtext', $result[0]);
    $this->assertArrayHasKey('vehicleid', $result[0]);
    $this->assertArrayHasKey('estimatedtime', $result[0]);
    $this->assertArrayHasKey('expiretime', $result[0]);
    $this->assertArrayHasKey('recordtime', $result[0]);
    $this->assertArrayHasKey('uniqueid', $result[0]);
  }

  // tests function get_connection()
  public function test_get_connection() {
    $db = new Database();
    $DBH = $db->get_connection();
    $sql = "SELECT * FROM phpunittest WHERE recordtime = '2016-07-27 00:00:26'";

    try {
      $database_obj = $DBH->prepare($sql);
      $database_obj->execute();
    }
    catch(PDOException $e) {
      echo $e->getMessage();
    }

    $result = $database_obj->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals('3077', $result[0]['stopid']);
    $this->assertEquals('20160725108720', $result[0]['uniqueid']);
    $this->assertNotEquals('1234', $result[0]['stopid']);
    $this->assertCount(8, $result[0]); // 8 columns, 1 row
    $this->assertArrayHasKey('stopid', $result[0]);
    $this->assertArrayHasKey('visitnumber', $result[0]);
    $this->assertArrayHasKey('destinationtext', $result[0]);
    $this->assertArrayHasKey('vehicleid', $result[0]);
    $this->assertArrayHasKey('estimatedtime', $result[0]);
    $this->assertArrayHasKey('expiretime', $result[0]);
    $this->assertArrayHasKey('recordtime', $result[0]);
    $this->assertArrayHasKey('uniqueid', $result[0]);
  }

  /**
   * @expectedException "PDOException"
  */
  public function testTypeException() {
    $db = new Database("", "146.169.47.42", "bus_data", 
    	      	       "testuser", "testpassword"); // no type
  }

  /**
   * @expectedException "PDOException"
  */
  public function testIPException() {
    $db = new Database("pgsql", "", "bus_data", 
    	      	       "testuser", "testpassword"); // no IP
  }

  /**
   * @expectedException "PDOException"
  */
  public function testNameException() {
    $db = new Database("pgsql", "146.169.47.42", "", 
    	      	       "testuser", "testpassword"); // no database
  }

  /**
   * @expectedException "PDOException"
  */
  public function testUsernameException() {
    $db = new Database("pgsql", "146.169.47.42", "bus_data", 
    	      	       "", "testpassword"); // no username
  }

  /**
   * @expectedException "PDOException"
  */
  public function testPasswordException() {
    $db = new Database("pgsql", "146.169.47.42", "bus_data", 
    	      	       "testuser", ""); // no password
  }
  
}
