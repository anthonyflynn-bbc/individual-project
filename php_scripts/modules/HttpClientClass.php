<?php

class HttpClient {
  protected $username = "LiveBus77966";
  protected $password = "ChUdA6weye";
  protected $curl; // curl resource
  protected $url; // URL from which to source data
  protected $fp; // file pointer for output data

  // Constructor
  function __construct($url, $fp) {
    $this->url = $url;
    $this->fp = $fp;
    $this->curl = curl_init();
    $this->set_options();
  }

  // Function to set relevant options (e.g. username, password, connection timeout etc.)
  function set_options() {
    curl_setopt($this->curl, CURLOPT_URL, $this->url);
    curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST); // Digest authorisation
    curl_setopt($this->curl, CURLOPT_USERPWD, $this->username.":".$this->password);
    curl_setopt($this->curl, CURLOPT_TIMEOUT, 99999999); // Long-lived connection
    curl_setopt($this->curl, CURLOPT_FILE, $this->fp);
  }

  // Function to start session (begin data collection)
  function start_data_collection() {
    curl_exec($this->curl);
  }

  // Function to close session
  function close_connection() {
    curl_close($this->curl);
  }
}
?>