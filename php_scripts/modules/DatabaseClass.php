<?php

class Database {
  protected $DBH; // database connection
  protected $database_type = 'pgsql';
  protected $server_ip = "";
  protected $database_name = "bus_data";
  protected $username = "";
  protected $password = "";

  // Constructor
  function __construct() {
    $this->connect();
  }

  // Function returns the database object connection
  function get_connection() {
    return $this->DBH;
  }

  // Function forms a connection to the bus_data database
  protected function connect() {
    try {
      $this->DBH = new PDO("$this->database_type:host=$this->server_ip;dbname=$this->database_name",
		            $this->username,$this->password);
      return $this->DBH;
    }
    catch(PDOException $e) {
      echo $e->getMessage()."\n";
      exit(1);
    }
  }

  // Function executes an sql statement & returns an array containing the response
  function execute_sql($sql_statement) {
    try {
      $database_obj = $this->DBH->prepare($sql_statement);
      $database_obj->execute(); // executes the prepared statement;
    }
    catch(PDOException $e) {
      echo $e->getMessage()."\n";
    }
  
    // returns executed database object:
    return $database_obj;
  }
}

?>
