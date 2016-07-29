<?php

// DatabaseClass.php
// Anthony Miles Flynn
// (27/07/16)
// Forms a connection to the database specified in the constructor parameters
// or connects to the default parameters if none provided

class Database {
  private $DBH; // PDO instance (database connection)
  private $database_type;
  private $server_ip;
  private $database_name;
  private $username;
  private $password;

  // Constructor
  public function __construct($database_type = "pgsql", 
  	 	  	      $server_ip = "",
  	   	       	      $database_name = "bus_data", 
			      $username = "",
		       	      $password = "") {
    $this->database_type = $database_type;
    $this->server_ip = $server_ip;
    $this->database_name = $database_name;
    $this->username = $username;
    $this->password = $password;
    $this->connect(); // call method to connect to database
  }

  // Function returns the database object connection
  public function get_connection() {
    return $this->DBH;
  }

  // Function forms a connection to the bus_data database
  protected function connect() {
    // form the data source name for the database
    $dsn = "$this->database_type:host=$this->server_ip;"
    	  ."dbname=$this->database_name"; 

    // attempt to connect to the database
    try {
      $this->DBH = new PDO($dsn, $this->username, $this->password);
      return $this->DBH;
    }
    catch(PDOException $e) {
      echo $e->getMessage()."\n";
      return;
    }
    echo "Database connection successful.\n";
  }

  // Function executes an sql statement & returns an array containing response
  public function execute_sql($sql_statement) {
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
