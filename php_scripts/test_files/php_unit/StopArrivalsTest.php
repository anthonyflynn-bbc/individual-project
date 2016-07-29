<?php

use phpunit\framework\TestCase;
require_once('/data/individual_project/php/modules/StopArrivalsClass.php');

class StopArrivalsTest extends TestCase {
  // Loads sample data into test tables
  public function setup_database() {
    $db = new Database();
    $sql = "DELETE FROM phpunit_sample_predictions_live ";
    $db->execute_sql($sql);
    $sql = "DELETE FROM phpunit_sample_arrivals_live ";
    $db->execute_sql($sql);
    $sql = "INSERT INTO phpunit_sample_predictions_live "
          ."SELECT * from phpunit_sample_predictions";
    $db->execute_sql($sql);
  }

  // Tests correct number of rows being extracted from predictions table
  // into arrivals table
  public function test_arrivals() {
    $db = new Database();
    $this->setup_database();
    
    $start_unix_time = 1469768400;
    $end_unix_time = $start_unix_time + 4 * 60 * 60;

    $arrival = new StopArrivals($start_unix_time, $end_unix_time,
				"phpunit_sample_predictions_live", 
				"phpunit_sample_arrivals_live");
    $arrival->complete_batch();

    $sql = "SELECT COUNT(*) FROM phpunit_sample_arrivals_live ";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals(89, $result[0]['count']);

    $sql = "SELECT COUNT(*) FROM phpunit_sample_predictions_live ";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals(0, $result[0]['count']);

    $sql = "SELECT * FROM phpunit_sample_arrivals_live "
    	  ."WHERE uniqueid='20160729010000' AND stopid='1039'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result);
    $this->assertEquals('1039', $result[0]['stopid']);
    $this->assertEquals(1, $result[0]['visitnumber']);
    $this->assertEquals('Chiswick', $result[0]['destinationtext']);
    $this->assertEquals('14591', $result[0]['vehicleid']);
    $this->assertEquals('2016-07-29 07:26:25', $result[0]['estimatedtime']);
    $this->assertEquals('1970-01-01 01:00:00', $result[0]['expiretime']);
    $this->assertEquals('2016-07-29 07:26:55', $result[0]['recordtime']);
    $this->assertEquals('20160729010000', $result[0]['uniqueid']);

    $sql = "SELECT * FROM phpunit_sample_arrivals_live "
    	  ."WHERE uniqueid='20160729010000'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(45, $result);

    $sql = "SELECT * FROM phpunit_sample_arrivals_live "
    	  ."WHERE uniqueid='20160729020000'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(44, $result);
  }

  // Tests functionality when there are two final predictions lines for the
  // same unqiueid + stopid + visitnumber processed in the same second
  public function test_duplicate_arrival() {
    $db = new Database();
    $this->setup_database();

    //Insert a duplicate arrival row before processing data
    $sql = "INSERT INTO phpunit_sample_predictions_live "
          ."VALUES ('1039',1,'Chiswick','14591','2016-07-29 07:26:25',
	    	    '1970-01-01 01:00:00','2016-07-29 07:26:55','20160729010000')";
    $db->execute_sql($sql);

    $start_unix_time = 1469768400;
    $end_unix_time = $start_unix_time + 4 * 60 * 60;

    $arrival = new StopArrivals($start_unix_time, $end_unix_time,
				"phpunit_sample_predictions_live", 
				"phpunit_sample_arrivals_live");
    $arrival->complete_batch();
    $sql = "SELECT * FROM phpunit_sample_arrivals_live "
    	  ."WHERE uniqueid='20160729010000'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(46, $result);
  }

}
