<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

 	//This is needed later to figure out if the student has graduated or not.
	$CurrentTime = time();


	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli->connect_errno) {
		echo "Failed to connect to SQL server: ".$mysqli->connect_error;
		goto SendTailer;
	}


	//Get all the course number records.
//    $SQLQuery = "SELECT ID, CreateUID, CreateTime, Field, Value FROM SPNRequestRecords WHERE Field='Course' ORDER BY Value;";
   $SQLQuery = "SELECT SPNRequests.SID, SPNRequest.CreateTime, SPNRequest.Course, SPNRequest.Section FROM SPNRequests
        LEFT JOIN
            (SELECT SID,
                (SELECT Value FROM SPNRequestRecords WHERE Field='CreateTime' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS CreateTime,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Course' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Course,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Section' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Section
            FROM SPNRequests) AS SPNRequest
        ON SPNRequests.SID=SPNRequest.SID
		ORDER BY Course;";
    $SQLResult1 = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        echo "SQL error ".$mysqli->error." on query ".$SQLQuery;
        goto CloseSQL;
    }


	//Go through all the course number records.
	while($Fields1 = $SQLResult1->fetch_assoc()) {

		$CourseNumber = $Fields1["Course"];
		$CourseSection = $Fields1["Section"];

		//Lookup the CourseID based on the course number.


//		$SQLQuery = "SELECT ID, CreateUID, CreateTime, Field, Value FROM CourseRecords WHERE Field='Number' AND Value='".$CourseNumber."';";

		$SQLQuery = "SELECT Courses.CID, Course.Number, Course.Section FROM Courses
        LEFT JOIN
            (SELECT CID,
                (SELECT Value FROM CourseRecords WHERE Field='Number' AND ID=Courses.CID ORDER BY CreateTime DESC LIMIT 1) AS Number,
                (SELECT Value FROM CourseRecords WHERE Field='Section' AND ID=Courses.CID ORDER BY CreateTime DESC LIMIT 1) AS Section
            FROM Courses) AS Course
        ON Courses.CID=Course.CID
		WHERE Number='".$CourseNumber."' AND Section='".$CourseSection."';"; 

		$SQLResult2 = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "SQL error ".$mysqli->error." on query ".$SQLQuery;
			goto CloseSQL;
		}

		//Only one course should be found, since no changes have been made to the CourseRecords at this time.
		if ($SQLResult2->num_rows == 1) {
			$Fields2 = $SQLResult2->fetch_assoc();
			$CourseID = $Fields2["CID"];
			echo "Found Course ".$CourseNumber." section ".$CourseSection." and matching CourseID ".$CourseID."\n";
		}
		else {
			echo "Failed to find CourseID for Course Number ".$CourseNumber." Section Number ".$CourseSection.", Records Found ".$SQLResult2->num_rows."\n";
		}
	}


CloseSQL:
    //Done with SQL server.
    $mysqli->close();

SendTailer:

?>


<?php
    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNCreateList.log"); 
    }

?>
