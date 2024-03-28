<?php
	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

	//This is needed later to figure out if the student has graduated or not.
	$CurrentTime = time();
    $MonthFromNow = $CurrentTime + 2592000;
    $ThreeMonthsAgo = $CurrentTime - 7776000;

    echo "Emailing return reminders to graduating students with keys.\n\r";
    WriteLog("Started.");

	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli->connect_errno) {
		WriteLog("Failed to connect to SQL server: ".$mysqli->connect_error);
		echo "Failed to connect to the MySQL database server: ".$mysqli->connect_error." \r\n";
		goto AllDone;
	}


    //First we need a list of all the students that are graduating soon.
    $SQLQuery = "SELECT Users.UID, People.FirstName, People.LastName, People.OfficialEmail, People.DepartureDateUTC, People.UserStatus FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='OfficialEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS OfficialEmail,
                (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
            FROM Users) AS People
        ON Users.UID=People.UID
        WHERE (DepartureDateUTC>".$ThreeMonthsAgo.") AND (DepartureDateUTC<".$MonthFromNow.");";
//        WHERE (DepartureDateUTC>".$CurrentTime.") AND (DepartureDateUTC<".$MonthFromNow.");";
    $SQLResult1 = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "Failed to get users list: ".$mysqli->error."\r\n";
        goto CloseSQL;
    }


    //Go through all the students found.
    if ($SQLResult1->num_rows > 0) {

        echo $SQLResult1->num_rows." students graduating in the next 30 days.\r\n";
        while ($Fields1 = $SQLResult1->fetch_assoc()) {

            echo "Student ID ".$Fields1["UID"].", ".$Fields1["FirstName"]." ".$Fields1["LastName"].", Graduating ".date("F j, Y", $Fields1["DepartureDateUTC"]).", Email: ".$Fields1["OfficialEmail"]."\r\n";

            //Get a list of all the keys they borrowed.
            $SQLQuery = "SELECT Keys.KID, Key.Status, Key.UserID, Key.RoomNumber, Key.KeyCode, Key.KeyNumber FROM `Keys`
                LEFT JOIN
                    (SELECT KID,
                        (SELECT Value FROM KeyRecords WHERE Field='Status' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				        (SELECT Value FROM KeyRecords WHERE Field='UserID' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS UserID,
				        (SELECT Value FROM KeyRecords WHERE Field='RoomNumber' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS RoomNumber,
                        (SELECT Value FROM KeyRecords WHERE Field='KeyCode' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyCode,
                        (SELECT Value FROM KeyRecords WHERE Field='KeyNumber' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyNumber
                    FROM `Keys`) AS `Key`
                ON Keys.KID=Key.KID
                WHERE (UserID=".$Fields1["UID"].") AND (Status='L');";
            $SQLResult2 = $mysqli->query($SQLQuery);
            if ($mysqli->error) {
                WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		        echo "Failed to get key list: ".$mysqli->error."\r\n";
                goto CloseSQL;
            }

            //Check if they still have any keys.
            if ($SQLResult2->num_rows > 0) {

                //Setup the email message.
                $EmailMessage = "Dear ".$Fields1["FirstName"]." ".$Fields1["LastName"].",\r\n\r\nIt appears that you will be graduating in the next 30 days. If this is correct, please be sure to return any keys that you have borrowed before you finally depart. Based on our records, you have borrowed at least the following keys:\r\n\r\n";

                while ($Fields2 = $SQLResult2->fetch_assoc()) {
                    $EmailMessage .= " Room ".$Fields2["RoomNumber"].", Key Code ".$Fields2["KeyCode"]."\r\n";
                }

                $EmailMessage .= "\r\nIf we have an incorrect graduation date for you, or you have already returned your keys, please let us know and we will update our records. Thanks!";
echo $EmailMessage."\r\n";
    		    mail($Fields1["OfficialEmail"], "Borrowed Keys", $EmailMessage, "From: keys@apps.ece.rutgers.edu\r\nCC: kevin.wine@rutgers.edu");

	    	    //Write a change record to note the fact that a pickup email was sent.
        		UpdateField($Fields1["UID"], '51', $CurrentTime, "Notes", "Return Keys email for ".$SQLResult2->num_rows." keys sent to ".$Fields1["OfficialEmail"].".");
            }
        }
    }


CloseSQL:
    //Done with SQL server.
    $mysqli->close();

AllDone:

?>


<?php
    //Function to write a string to the log. I've never had this many problems writing a log file. Make sure directory permissions are 775! Must use absolute path!
    // Let apache create the file. Owner will be apache:ece
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Orders/OrdersSendReminders.log"); 
    }


	//Stores the field to a name/value record in the change record table.
	//Returns TRUE if the field was created or changed, FALSE if it wasn't or if there was an error.
	function UpdateField($IDBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM UserRecords WHERE ID=".$IDBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "<P>Failed to retrieve change record: ".$mysqli->error."</P>";
			return FALSE;
		}

		//New field blank, Old field exists -> Write new record
		//New field blank, Old field not exists -> Do nothing
		//New field has text, Old field exists -> Write a new record if value is different
		//New field has text, Old field not exists -> Write a new record

		//This is the case of the field not in the database and the new field is either non-existant or blank.
		if ( ($SQLResult->num_rows < 1) && (strlen($FieldValue) == 0) )
			return FALSE;

		//This is the case of the field already in the database and it matches the new field.
		if ($SQLResult->num_rows == 1) {
			$Fields = $SQLResult->fetch_row();
			if ($Fields[0] == $FieldValue)
				return FALSE;
		}

		//If we end up here it is OK to go ahead and save the new name/value pair.
		$SQLQuery = "INSERT INTO UserRecords (
			ID,
			CreateUID,
			CreateTime,
			Field,
			Value
		) VALUES (
			'".$IDBeingUpdated."',
			'".$UserDoingUpdate."',
			'".$TimeStamp."',
			'".$FieldName."',
			'".mysqli_real_escape_string($mysqli, $FieldValue)."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "<P>Failed to add change record: ".$mysqli->error."</P>";
			return FALSE;
		}

		return TRUE;
    }

?>