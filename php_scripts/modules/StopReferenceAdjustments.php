<?php

// StopReferenceAdjustments.php
// Anthony Miles Flynn
// (12/08/16)
// Class to correct incorrect stop reference reference

include_once '/data/individual_project/php/modules/DatabaseClass.php';

class Corrections {
  private $database;
  private $DBH;
  private $stop_table; // stop reference data

  // Constructor
  public function __construct($stop_table = "stop_reference") {
    $this->database = new Database();
    $this->DBH = $this->database->get_connection();
    $this->stop_table = $stop_table;
  }

  // Function to update incorrect stop reference data
  public function complete_update() {
    $corrections = array(array('stopid'=>"BP6077",
			       'stopcode2'=>"490018760E"),
			 array('stopid'=>"BP6078",
			       'stopcode2'=>"490018760W"),
			 array('stopid'=>"34329",
			       'stopcode2'=>"490003564W"),
			 array('stopid'=>"34927",
				'stopcode2'=>"490003564E"),
			 array('stopid'=>"BP5782",
				'stopcode2'=>"4900001127H"),
			 array('stopid'=>"HC252",
				'stopcode2'=>"490000409Z"),
			 array('stopid'=>"HC476",
				'stopcode2'=>"490000410Z"),
			 array('stopid'=>"HC478",
				'stopcode2'=>"490000417Z"),
			 array('stopid'=>"HC480",
				'stopcode2'=>"490000418Z"),
			 array('stopid'=>"HC255",
				'stopcode2'=>"490014509E"),
			 array('stopid'=>"HC482",
				'stopcode2'=>"490000420Z"),
			 array('stopid'=>"HC484",
				'stopcode2'=>"490000421Y"),
			 array('stopid'=>"HC486",
				'stopcode2'=>"490000422Z"),
			 array('stopid'=>"HC256",
				'stopcode2'=>"490016766E"),
			 array('stopid'=>"HC260",
				'stopcode2'=>"490016769S"),
			 array('stopid'=>"HC488",
				'stopcode2'=>"490010485Z"),
			 array('stopid'=>"HC490",
				'stopcode2'=>"490000423Y"),
			 array('stopid'=>"HC257",
				'stopcode2'=>"490006574Z"),
			 array('stopid'=>"HC258",
				'stopcode2'=>"490013151W"),
			 array('stopid'=>"BP5859",
				'stopcode2'=>"4900020148S"),
			 array('stopid'=>"BP6052",
				'stopcode2'=>"490000004Z"),
			 array('stopid'=>"26646",
				'stopcode2'=>"490010273S"),
			 array('stopid'=>"33669",
				'stopcode2'=>"490004844S"),
			 array('stopid'=>"OC975",
				'stopcode2'=>"490001279Z"),
			 array('stopid'=>"OC472",
				'stopcode2'=>"210021007930"),
			 array('stopid'=>"OC445",
				'stopcode2'=>"210021903896"),
			 array('stopid'=>"BP2136",
				'stopcode2'=>"490005563F"),
			 array('stopid'=>"BP5774",
				'stopcode2'=>"490000286Z"),
			 array('stopid'=>"35591",
				'stopcode2'=>"490011309Z"),
			 array('stopid'=>"BP5906",
				'stopcode2'=>"490003936S"),
			 array('stopid'=>"BP4741",
				'stopcode2'=>"150042005009"),
			 array('stopid'=>"BP5755",
				'stopcode2'=>"490000015X"),
			 array('stopid'=>"2567",
				'stopcode2'=>"490003246Z"),
			 array('stopid'=>"BP5995",
				'stopcode2'=>"490000356NE"),
			 array('stopid'=>"BP5880",
				'stopcode2'=>"490000301N"),
			 array('stopid'=>"HC386",
				'stopcode2'=>"490001024X"),
			 array('stopid'=>"HC388",
				'stopcode2'=>"490019478Z"),
			 array('stopid'=>"HC390",
				'stopcode2'=>"490007819Z"),
			 array('stopid'=>"HC392",
				'stopcode2'=>"490016726S"),
			 array('stopid'=>"HC394",
				'stopcode2'=>"490001024X"),
			 array('stopid'=>"HC396",
				'stopcode2'=>"490006449W"),
			 array('stopid'=>"HC398",
				'stopcode2'=>"490016727Z"),
			 array('stopid'=>"HC400",
				'stopcode2'=>"490011390S"),
			 array('stopid'=>"HC402",
				'stopcode2'=>"490016791Z"),
			 array('stopid'=>"HC404",
				'stopcode2'=>"490003286E"),
			 array('stopid'=>"HC406",
				'stopcode2'=>"490004632Z"),
			 array('stopid'=>"HC408",
				'stopcode2'=>"490016728E"),
			 array('stopid'=>"HC185",
				'stopcode2'=>"490019479S"),
			 array('stopid'=>"HC110",
				'stopcode2'=>"490019479Z"),
			 array('stopid'=>"HC186",
				'stopcode2'=>"49000HC186NE"),
			 array('stopid'=>"HC410",
				'stopcode2'=>"490006443Z"),
			 array('stopid'=>"HC412",
				'stopcode2'=>"490012727Y"),
			 array('stopid'=>"HC414",
				'stopcode2'=>"490010242E"),
			 array('stopid'=>"HC416",
				'stopcode2'=>"490011459N"),
			 array('stopid'=>"HC417",
				'stopcode2'=>"490013756Z"),
			 array('stopid'=>"HC418",
				'stopcode2'=>"490011216Z"),
			 array('stopid'=>"BP5807",
				'stopcode2'=>"49009917N"),
			 array('stopid'=>"OC240",
				'stopcode2'=>"210021903892"),
			 array('stopid'=>"HC415",
				'stopcode2'=>"490008548NE"),
			 array('stopid'=>"HC413",
				'stopcode2'=>"490011216Y"),
			 array('stopid'=>"HC411",
				'stopcode2'=>"490013756Y"),
			 array('stopid'=>"HC409",
				'stopcode2'=>"490010242W"),
			 array('stopid'=>"HC407",
				'stopcode2'=>"490005953S"),
			 array('stopid'=>"HC405",
				'stopcode2'=>"490012727Z"),
			 array('stopid'=>"HC112",
				'stopcode2'=>"490019331Z"),
			 array('stopid'=>"HC403",
				'stopcode2'=>"490004632Y"),
			 array('stopid'=>"HC401",
				'stopcode2'=>"490003286W"),
			 array('stopid'=>"HC399",
				'stopcode2'=>"490016791Y"),
			 array('stopid'=>"HC397",
				'stopcode2'=>"490011390Z"),
			 array('stopid'=>"HC395",
				'stopcode2'=>"490001024Y"),
			 array('stopid'=>"HC393",
				'stopcode2'=>"490016726Z"),
			 array('stopid'=>"HC391",
				'stopcode2'=>"490004652E"),
			 array('stopid'=>"HC389",
				'stopcode2'=>"490007819Y"),
			 array('stopid'=>"HC387",
				'stopcode2'=>"490019478Y"),
			 array('stopid'=>"BP5759",
				'stopcode2'=>"490000284FQ"),
			 array('stopid'=>"9451",
				'stopcode2'=>"490006476E"),
			 array('stopid'=>"HC551",
				'stopcode2'=>"490000449Y"),
			 array('stopid'=>"BP5801",
				'stopcode2'=>"490000290E"),
			 array('stopid'=>"BP5603",
				'stopcode2'=>"490019793W"),
			 array('stopid'=>"BP4925",
				'stopcode2'=>"490023076W"),
			 array('stopid'=>"BP5819",
				'stopcode2'=>"490020135E"),
			 array('stopid'=>"BP5829",
				'stopcode2'=>"490020149F"),
			 array('stopid'=>"33918",
				'stopcode2'=>"490004984E"));

    $sql = "UPDATE $this->stop_table "
     	  ."SET stopcode2 = :stopcode2 "
	  ."WHERE stopid = :stopid";

    $update_sql = $this->DBH->prepare($sql);

    foreach($corrections as $entry) {
      $update_sql->bindValue(':stopid', $entry['stopid'],PDO::PARAM_STR);
      $update_sql->bindValue(':stopcode2', $entry['stopcode2'],PDO::PARAM_STR);
      $update_sql->execute();
    }

    // Adjustments where duplicate stopcode2 for 2 different stopids:
    $duplicate_array = array("BP437", "17909", "34804", "35427", "BP437",
		             "BP4575", "BP5746", "H0204", "H0205", "H0429");

    $delete_sql = "UPDATE $this->stop_table "
	         ."SET stopcode2 = null "
	         ."WHERE stopid = :stopid";

    $delete_stopcode2 = $this->DBH->prepare($delete_sql);

    foreach($duplicate_array as $stopid) {
      $delete_stopcode2->bindValue(':stopid', $stopid, PDO::PARAM_STR);
      $delete_stopcode2->execute();
    }
  }
}


$p = new Corrections();
$p->complete_update();

?>