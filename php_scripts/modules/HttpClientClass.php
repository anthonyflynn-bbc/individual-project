<?php

// HttpClientClass.php
// Anthony Miles Flynn
// (29/07/16)
// Forms an Http connection to the url ($url) specified in the constructor 
// parameters and saves the data received to the file pointer ($fp) specified
// in the constructor parameters.  Uses the default username and password unless
// alternatives are provided on instantiation.

class HttpClient {
  private $username;
  private $password;
  private $url; // URL from which to source data
  private $fp; // file pointer for output data
  private $curl; // curl resource

  // Constructor
  public function __construct($url, $fp, $username = "",
  	   	       $password = "") {
    $this->url = $url;
    $this->fp = $fp;
    $this->username = $username;
    $this->password = $password;
    $this->curl = curl_init();
    $this->set_options();
  }

  // Function to set relevant options (e.g. username, password, timeout etc.)
  private function set_options() {
    curl_setopt($this->curl, CURLOPT_URL, $this->url);
    curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); // authorisation
    curl_setopt($this->curl, CURLOPT_USERPWD, $this->username.":".$this->password);
    curl_setopt($this->curl, CURLOPT_TIMEOUT, 99999999); //Long-lived connection
    curl_setopt($this->curl, CURLOPT_FILE, $this->fp); //save data to file pointer
  }

  // Function to start session (begin data collection)
  public function start_data_collection() {
    if(curl_exec($this->curl) === false) { // error connecting to url
      echo "Error collecting data.\n";
      return false;
    }
    echo "Data collection started.\n";
    return true;
  }

  // Function to close session
  public function close_connection() {
    curl_close($this->curl); // no return value
    echo "Curl connection closed.\n";
  }
}
?>
