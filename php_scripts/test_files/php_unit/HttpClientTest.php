<?php

// HttpClientTest.php
// Anthony Miles Flynn
// (08/09/16)
// PHPUnit test file for HttpClient class

use phpunit\framework\TestCase;
require_once('/data/individual_project/php/modules/HttpClientClass.php');

class HttpClientTest extends TestCase {
  // Tests function start_data_collection
  public function test_start_data_collection() {
    $fp = fopen("http_test.txt","w+");
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/instant_V1?"
    	  ."ReturnList=Baseversion";
    $http = new HttpClient($url, $fp);
    $this->assertTrue($http->start_data_collection());
    $http->close_connection();
    fclose($fp);

    $fp = fopen("http_test.txt","r");
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

  // Tests input of a non-existent url
  public function test_incorrect_url() {
    $fp = fopen("http_test.txt","w+");
    $url = "incorrect_url_string";
    $http = new HttpClient($url, $fp);
    $this->assertFalse($http->start_data_collection());
    $http->close_connection();
    fclose($fp);
  }    

  // Tests incorrect username
  public function test_incorrect_username() {
    $fp = fopen("http_test.txt","w+");
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?"
    	  ."ReturnList=Baseversion";
    $http = new HttpClient($url, $fp, "false_username", "false_password");
    $http->close_connection();
    fclose($fp);
    $fp = fopen("http_test.txt","r");
    $character = fgetc($fp);
    $this->assertEquals(null,$character);
  }    

  // Tests incorrect password
  public function test_incorrect_password() {
    $fp = fopen("http_test.txt","w+");
    $url = "http://countdown.api.tfl.gov.uk/interfaces/ura/stream_V1?"
    	  ."ReturnList=Baseversion";
    $http = new HttpClient($url, $fp, null, "false_password");
    $http->close_connection();
    fclose($fp);
    $fp = fopen("http_test.txt","r");
    $character = fgetc($fp);
    $this->assertEquals(null,$character);
  }    

}
