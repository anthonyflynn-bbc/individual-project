<?php

use phpunit\framework\TestCase;
require_once('StopReferenceUpdateFunctions.php'); //public function versions

class StopReferenceTest extends TestCase {
  // Function to delete any old test data from test databases
  public function delete_from_test_databases() {
    $db = new Database();
    $sql = "DELETE FROM phpunit_stop_reference";
    $db->execute_sql($sql);
    $sql = "DELETE FROM phpunit_stop_reference_temp";
    $db->execute_sql($sql);
  }

  // Function to prepare databases for update tests
  public function prepare_databases() {
    // prepare database with sample data:
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();

    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $contents = file_get_contents("sample_data.txt"); // get sample data
    fwrite($functions->fp, $contents); // write to phpunit temp database
    
    // copy to permanent database:
    $sql = "INSERT INTO phpunit_stop_reference "
          ."SELECT * FROM phpunit_stop_reference_temp";
    $db->execute_sql($sql);
    $sql = "DELETE FROM phpunit_stop_reference_temp";
    $db->execute_sql($sql);

    // load temporary database contents
    $contents = file_get_contents("sample_data_new.txt"); // get sample data
    fwrite($functions->fp, $contents); // write to phpunit temp database
  }

  // Checks loading of previous version from txt file functioning correctly
  public function test_get_previous_version() {
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $version_text = $functions->get_previous_version();
    $this->assertEquals("20160721", $version_text);
  } 

  // Tests correct access to TfL feed to download baseversion array
  public function test_get_baseversion_array() {
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $ura_baseversion_array = $functions->get_baseversion_array();
    $ura = str_getcsv(trim($ura_baseversion_array[0], "[]\n\r"));
    $baseversion = str_getcsv(trim($ura_baseversion_array[1], "[]\n\r"));
    $this->assertEquals(4, $ura[0]);
    $this->assertEquals(3, $baseversion[0]);
    $this->assertEquals(8, strlen($baseversion[1]));
  }

  // Tests get_current_version() returns an array of the correct format
  public function test_get_current_version() {
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $baseversion = $functions->get_current_version();
    $this->assertEquals(8, strlen($baseversion));
    $this->assertEquals("2016", substr($baseversion, 0, 4));
    
    // test $baseversion_array which doesn't contain a baseversion
    $functions->baseversion_array = array();
    $baseversion = $functions->get_current_version();
    $this->assertEquals(-1, $baseversion); 
    $this->assertNotEquals(-2, $baseversion); 
  }

  // Tests function which determines if an update is required
  public function test_check_version() {
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $functions->previous_version = "20160723";
    $functions->current_version = "20160723";
    $this->assertFalse($functions->update_required());

    $functions->current_version = "20160724";
    $this->assertTrue($functions->update_required());
  }

  // Tests make_database_updates function
  public function test_make_database_updates() {
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $this->prepare_databases();

    $temp_sql = "SELECT * from phpunit_stop_reference_temp "
    	       ."WHERE stopid='33493'";
    $temp_result = $db->execute_sql($temp_sql)->fetchAll(PDO::FETCH_ASSOC);
    $perm_sql = "SELECT * from phpunit_stop_reference "
    	       ."WHERE stopid='33493'";
    $perm_result = $db->execute_sql($perm_sql)->fetchAll(PDO::FETCH_ASSOC);

    $this->assertEquals("Fake name", $temp_result[0]['stoppointname']); 
    $this->assertCount(1, $temp_result);
    $this->assertEquals("Scarborough Road", $perm_result[0]['stoppointname']); 
    $this->assertCount(1, $perm_result);

    $functions->make_database_updates();

    $temp_sql = "SELECT * from phpunit_stop_reference_temp "
    	       ."WHERE stopid='33493'";
    $temp_result = $db->execute_sql($temp_sql)->fetchAll(PDO::FETCH_ASSOC);
    $perm_sql = "SELECT * from phpunit_stop_reference "
    	       ."WHERE stopid='33493'";
    $perm_result = $db->execute_sql($perm_sql)->fetchAll(PDO::FETCH_ASSOC);

    $this->assertEquals("Fake name", $temp_result[0]['stoppointname']); 
    $this->assertCount(1, $temp_result);
    $this->assertEquals("Fake name", $perm_result[0]['stoppointname']); 
    $this->assertCount(1, $perm_result);

    
  }

  // Tests function which gets differences between the new and old data
  public function test_get_difference() {
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $this->prepare_databases();

    $result = $functions->get_difference("phpunit_stop_reference_temp", 
			                 "phpunit_stop_reference");
    $this->assertCount(1 , $result); // one new addition

    $result = $functions->get_difference("phpunit_stop_reference", 
			                 "phpunit_stop_reference_temp");
    $this->assertCount(1 , $result); // one removal
  }

  // Tests correct insertion into permanent database
  public function test_make_insertion() {
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $contents = file_get_contents("sample_data.txt"); // get sample data
    fwrite($functions->fp, $contents); // write to phpunit temp database

    // Test correct number of rows in temp database
    $temp_sql = "SELECT * FROM phpunit_stop_reference_temp";
    $result = $db->execute_sql($temp_sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(12, $result); // 12 data lines in sample dat
    $this->assertCount(11, $result[0]); // 11 columns in table
    $this->assertEquals("Gospel Oak Station", $result[0]['stoppointname']);

    // Check permanent database empty
    $perm_sql = "SELECT * FROM phpunit_stop_reference";
    $perm_result = $db->execute_sql($perm_sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(0, $perm_result);

    // Insert first row in temp datbase into permanent database
    $functions->make_insertion($result[0]); //copies across to permanent table
    $perm_result = $db->execute_sql($perm_sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $perm_result); // check row correctly copied across
  }

  // Tests correct functioning of database removal function
  public function test_make_removal() {
    // prepare database with sample data:
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $contents = file_get_contents("sample_data.txt"); // get sample data
    fwrite($functions->fp, $contents); // write to phpunit temp database

    $temp_sql = "SELECT * FROM phpunit_stop_reference_temp";
    $result = $db->execute_sql($temp_sql)->fetchAll(PDO::FETCH_ASSOC);

    $functions->make_insertion($result[0]); //copies across to permanent table
    $sql = "SELECT * FROM phpunit_stop_reference where stopid='34506'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result); // Should be one line with this stopid

    $functions->make_removal($result[0]);
    $new_result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(0, $new_result); // Result should now be removed
  }

  // Tests get_updates function
  public function test_get_updates() {
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $this->prepare_databases();

    $result = $functions->get_updates("phpunit_stop_reference_temp", 
			    	      "phpunit_stop_reference");
    $this->assertCount(11, $result);

    $result = $functions->get_updates("phpunit_stop_reference", 
			    	      "phpunit_stop_reference_temp");
    $this->assertCount(11, $result);
  }

  // Tests make_update function
  public function test_make_update() {
    $this->delete_from_test_databases(); // delete stale information
    $db = new Database();
    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version.txt");
    $this->prepare_databases();

    $result = $functions->get_difference("phpunit_stop_reference_temp", 
			                 "phpunit_stop_reference");
    $functions->make_update($result[0]);
    $sql = "SELECT * FROM phpunit_stop_reference "
          ."WHERE stoppointname = 'Felixstowe Road'";
    $result = $db->execute_sql($sql)->fetchAll(PDO::FETCH_ASSOC);
    $this->assertCount(1, $result);
  }

  // Function tests that save of latest version file is working
  public function test_save_latest_version() {
    $fp = fopen("test_version2.txt", "w+");
    fwrite($fp, "Sample Text");

    $file_contents = file_get_contents("test_version2.txt");
    $this->assertEquals("Sample Text", $file_contents);

    $functions = new StopReferenceFunctions("phpunit_stop_reference_temp",
					    "phpunit_stop_reference",
					    "test_version2.txt");
    $functions->current_version = "20160701";
    $functions->save_latest_version();
    
    $fp = fopen("test_version2.txt", "r");
    $file_contents = file_get_contents("test_version2.txt");
    $this->assertEquals("20160701", $file_contents);
  }

}
