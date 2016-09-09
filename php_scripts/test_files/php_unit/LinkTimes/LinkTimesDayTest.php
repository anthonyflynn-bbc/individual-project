<?php

// LinkTimesDayTest.php
// Anthony Miles Flynn
// (30/07/16)
// Program completes unit tests on LinkTimesDayClass.php

use phpunit\framework\TestCase;
//require_once('/data/individual_project/php/test_files/php_unit/LinkTimes/'
//	    .'LinkTimesDayFunctions.php');
require_once('/data/individual_project/php/modules/LinkTimesDateClass.php');
require_once('/data/individual_project/php/modules/LinkTimesDayClass.php');

class LinkTimesDayTest extends TestCase {
  // Deletes any old test data from test databases and loads correct data
  public function prepare_databases() {
    $db = new Database();
    $sql = "DELETE FROM phpunit_sample_arrivals_live";
    $db->execute_sql($sql);
    $sql = "DELETE FROM phpunit_link_times_day";
    $db->execute_sql($sql);
    $sql = "DELETE FROM phpunit_link_times_date";
    $db->execute_sql($sql);

    // insert test data into the test database:
    $sql = "INSERT INTO phpunit_sample_arrivals_live "
    	  ."SELECT * FROM phpunit_link_times_arrivals_day_data";
    $db->execute_sql($sql);

  }
  
  // process arrivals data into link_times_date_table
  public function process_date_information() {
    $this->prepare_databases();

    $test_time = 1469345400; // 08:30 on 24/7/2016
    $date_class = new LinkTimesDate($test_time,
				   "phpunit_sample_arrivals_live",
				   "route_reference",
				   "stop_reference",
				   "phpunit_link_times_date");
    $date_class->complete_update();

    $test_time = 1469950200; // 08:30 on 31/7/2016
    $date_class = new LinkTimesDate($test_time,
				   "phpunit_sample_arrivals_live",
				   "route_reference",
				   "stop_reference",
				   "phpunit_link_times_date");
    $date_class->complete_update();
  }


  // Tests complete_update function
  public function test_complete_update() {
    $this->process_date_information();

    $test_time = 1469950200; // 08:30 on 31/7/2016
    $full_class = new LinkTimesDay($test_time,
				  "phpunit_link_times_date",
				  "route_reference",
				  "stop_reference",
				  "phpunit_link_times_day");
    $full_class->complete_update();

/*
    // Test for first hour
    $db = new Database();
    $sql = "SELECT * FROM phpunit_link_times_date "
    	  ."WHERE start_stopid ='4726' AND end_stopid = '2391' "
	  ."AND hour = 0";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result);
    $this->assertEquals(47, $result[0]['link_time']);

    $sql = "SELECT * FROM phpunit_link_times_date "
    	  ."WHERE start_stopid ='517' AND end_stopid = '26761' "
	  ."AND hour = 0";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals(115, $result[0]['link_time']);

    // Test for second hour
    $sql = "SELECT * FROM phpunit_link_times_date "
    	  ."WHERE start_stopid ='4726' AND end_stopid = '2391' "
	  ."AND hour = 1";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result);
    $this->assertEquals(101, $result[0]['link_time']);

    $sql = "SELECT * FROM phpunit_link_times_date "
    	  ."WHERE start_stopid ='517' AND end_stopid = '26761' "
	  ."AND hour = 1";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertEquals(94, $result[0]['link_time']);

    // Test for third hour
    $sql = "SELECT * FROM phpunit_link_times_date "
	  ."WHERE hour > 2";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(0, $result);
*/

  }


/*
  // Tests extract_journey_times
  public function test_extract_journey_times() {
    $this->prepare_databases();

    // Test for whole day:
    $start_time = "'2016-07-30 00:00:00'";
    $end_time = "'2016-07-31 00:00:00'";

    $test_time = 1469950200; // 08:30 on 31/7/2016
    $functions = new LinkTimesDateFunctions($test_time, 
					    "phpunit_sample_arrivals_live",
					    "route_reference",
					    "stop_reference",
					    "phpunit_link_times_date");
    $result = $functions->extract_journey_times($start_time, $end_time);
    $this->assertCount(98, $result);
  }

  // Tests insert_into_database
  public function test_insert_into_database() {
    $this->prepare_databases();
    $test_time = 1469950200; // 08:30 on 31/7/2016
    $functions = new LinkTimesDateFunctions($test_time, 
					    "phpunit_sample_arrivals_live",
					    "route_reference",
					    "stop_reference",
					    "phpunit_link_times_date");

    // Test for first hour:
    $start_time = "'2016-07-30 00:00:00'";
    $end_time = "'2016-07-30 01:00:00'";
    $result = $functions->extract_journey_times($start_time, $end_time);
    $functions->insert_into_database($result, "'2016-07-30'" , 0);

    $db = new Database();
    $sql = "SELECT * FROM phpunit_link_times_date "
    	  ."WHERE start_stopid ='4726' AND end_stopid = '2391'"
	  ."AND hour = 0";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result);
    $this->assertEquals(47, $result[0]['link_time']);

    // Test for second hour:
    $start_time = "'2016-07-30 01:00:00'";
    $end_time = "'2016-07-30 02:00:00'";
    $result = $functions->extract_journey_times($start_time, $end_time);
    $functions->insert_into_database($result, "'2016-07-30'" , 1);

    $db = new Database();
    $sql = "SELECT * FROM phpunit_link_times_date "
    	  ."WHERE start_stopid ='4726' AND end_stopid = '2391'"
	  ."AND hour = 1";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result);
    $this->assertEquals(101, $result[0]['link_time']);
  }


  // Tests delete_old_data
  public function test_delete_old_data() {
    // Prepare databases for testing:
    $db = new Database();
    $sql = "DELETE FROM phpunit_sample_arrivals_live";
    $this->prepare_databases();

    $sql = "SELECT * FROM phpunit_sample_arrivals_live";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(126, $result);

    $test_time = 1469950200; // 08:30 on 31/7/2016
    $functions = new LinkTimesDateFunctions($test_time, 
					    "phpunit_sample_arrivals_live",
					    "route_reference",
					    "stop_reference",
					    "phpunit_link_times_date");
    $functions->delete_old_data();
    $sql = "SELECT * FROM phpunit_sample_arrivals_live";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(7, $result);

    $sql = "SELECT * FROM phpunit_sample_arrivals_live "
    	  ."WHERE estimatedtime < '2016-07-30 00:00:00'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(7, $result);
  }
*/
}

?>