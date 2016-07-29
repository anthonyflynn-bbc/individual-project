<?php

use phpunit\framework\TestCase;
require_once('/data/individual_project/php/modules/HttpClientClass.php');

class HttpClientTest extends TestCase {
  public function test_start_data_collection() {
    $fp = fopen("test.txt","w+");
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
    	  ."ReturnList=Baseversion";
    $http = new HttpClient($url, $fp);
    $this->assertTrue($http->start_data_collection());
    $http->close_connection();
    fclose($fp);

    $fp = fopen("test.txt","r");
    $character = fgetc($fp);
    $this->assertEquals("[",$character);
    $character = fgetc($fp);
    $this->assertEquals("4",$character);
    $line = fgets($fp);
    $character = fgetc($fp);
    $character = fgetc($fp);
    $this->assertEquals("3",$character);
    fclose($fp);
  }

  public function test_incorrect_url() {
    $fp = fopen("test.txt","w+");
    $url = "incorrect_url_string";
    $http = new HttpClient($url, $fp);
    $this->assertFalse($http->start_data_collection());
    $http->close_connection();
    fclose($fp);
  }    

  public function test_incorrect_username() {
    $fp = fopen("test.txt","w+");
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?"
    	  ."ReturnList=Baseversion";
    $http = new HttpClient($url, $fp, "false_username", "false_password");
    $http->close_connection();
    fclose($fp);
    $fp = fopen("test.txt","r");
    $character = fgetc($fp);
    $this->assertEquals(null,$character);
  }    

  public function test_incorrect_password() {
    $fp = fopen("test.txt","w+");
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?"
    	  ."ReturnList=Baseversion";
    $http = new HttpClient($url, $fp, null, "false_password");
    $http->close_connection();
    fclose($fp);
    $fp = fopen("test.txt","r");
    $character = fgetc($fp);
    $this->assertEquals(null,$character);
  }    

}
