<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

	//Connect to MySQL server.
    require('/www/custom/eceapps/include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
	if($mysqli -> connect_errno) {
		WriteLog("Failed to connect to SQL server: ".$mysqli->connect_error);
		exit();
	}

	//All table entries created in this script should have the same timestamp.
	$CurrentTime = time();

	$ThreeWeeks1 = $CurrentTime + 1814400;
	$ThreeWeeks2 = $CurrentTime + 1900800;

	$TwoWeeks1 = $CurrentTime + 1209600;
	$TwoWeeks2 = $CurrentTime + 1296000;

	$OneWeek1 = $CurrentTime + 604800;
	$OneWeek2 = $CurrentTime + 691200;

	//Log the start of the script.
	WriteLog("Daily Key Processing");
	//echo "Daily Key Processing\r\n";

    //First we need a list of all the students that are graduating in three weeks.
    $SQLQuery = "SELECT Users.UID, User.FirstName, User.LastName, User.OfficialEmail, User.PreferredEmail, User.DepartureDateUTC, User.UserStatus FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='OfficialEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS OfficialEmail,
                (SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PreferredEmail,
                (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
            FROM Users) AS User
        ON Users.UID=User.UID
        WHERE (DepartureDateUTC>=".$OneWeek1.") AND (DepartureDateUTC<".$ThreeWeeks2.") AND UserStatus='A';";
    $SQLResult1 = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		//echo "Failed to get users list: ".$mysqli->error."\r\n";
        goto CloseSQL;
    }


    //Go through all the students found.
    if ($SQLResult1->num_rows > 0) {

        WriteLog($SQLResult1->num_rows." students graduating over the next three weeks.");
        while ($Fields1 = $SQLResult1->fetch_assoc()) {

            //echo "Student ID ".$Fields1["UID"].", ".$Fields1["FirstName"]." ".$Fields1["LastName"].", Graduating ".date("F j, Y", $Fields1["DepartureDateUTC"]).", Official Email: ".$Fields1["OfficialEmail"].", Preferred Email: ".$Fields1["PreferredEmail"]."\r\n";

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
		        //echo "Failed to get key list: ".$mysqli->error."\r\n";
                goto CloseSQL;
            }

            //Check if they still have any keys.
            if ($SQLResult2->num_rows > 0) {

				//Emails are sent 3 weeks, 2 week, and 1 week before graduation, and a different email is sent for each one.

				//Setup the sending email address(s).
				if ($Fields1["OfficialEmail"] == $Fields1["PreferredEmail"])
					$EmailTo = $Fields1["PreferredEmail"];
				else if ( ($Fields1["OfficialEmail"] == "") && ($Fields1["PreferredEmail"] != "") )
					$EmailTo = $Fields1["PreferredEmail"];
				else if ( ($Fields1["OfficialEmail"] != "") && ($Fields1["PreferredEmail"] == "") )
					$EmailTo = $Fields1["OfficialEmail"];
				else if ( ($Fields1["OfficialEmail"] != "") && ($Fields1["PreferredEmail"] != "") )
					$EmailTo = $Fields1["OfficialEmail"].",".$Fields1["PreferredEmail"];
				else {
					$EmailTo = "";
	                WriteLog("Keys loanded but no email address for ".$Fields1["FirstName"]." ".$Fields1["LastName"]);
				}

				//Three weeks out.
				if ( ($Fields1["DepartureDateUTC"] >= $ThreeWeeks1) && ($Fields1["DepartureDateUTC"] < $ThreeWeeks2) && ($EmailTo != "") ) {

					//Setup the email message.
					$EmailMessage = "Dear ".$Fields1["FirstName"]." ".$Fields1["LastName"].",\r\n\r\nIt appears that you will be graduating in the near future. If this is correct, please prepare to return any keys that you have borrowed before you finally depart. Based on our records, you have borrowed at least the following keys:\r\n\r\n";
					while ($Fields2 = $SQLResult2->fetch_assoc()) {
						$EmailMessage .= " Room ".$Fields2["RoomNumber"].", Key Code ".$Fields2["KeyCode"]."\r\n";
					}
					$EmailMessage .= "\r\nIf we have an incorrect graduation date for you, or you have already returned your keys, please let us know and we will update our records. Thanks!\r\n\r\nSincerely,\r\nECE Key Tracking System";

					//echo $EmailMessage."\r\n";
    				mail($EmailTo, "ECE Borrowed Keys", $EmailMessage, "From: keys@apps.ece.rutgers.edu\r\nReply-To: kevin.wine@rutgers.edu\r\nCC: kevin.wine@rutgers.edu");

	    			//Write a change record to note the fact that a key reminder email was sent.
        			UpdateField($Fields1["UID"], '0', $CurrentTime, "Notes", "Three-week key reminder email sent to ".$EmailTo.".");

					WriteLog("Three-week email sent to ".$Fields1["FirstName"]." ".$Fields1["LastName"]." at ".$EmailTo." for ".$SQLResult2->num_rows." keys.");
				}

				//Two weeks out.
				if ( ($Fields1["DepartureDateUTC"] >= $TwoWeeks1) && ($Fields1["DepartureDateUTC"] < $TwoWeeks2) && ($EmailTo != "") ) {

					//Setup the email message.
					$EmailMessage = "Dear ".$Fields1["FirstName"]." ".$Fields1["LastName"].",\r\n\r\nIt appears that you will be graduating soon. If this is correct, please return any keys that you have borrowed before you finally depart. Based on our records, you have borrowed at least the following keys:\r\n\r\n";
					while ($Fields2 = $SQLResult2->fetch_assoc()) {
						$EmailMessage .= " Room ".$Fields2["RoomNumber"].", Key Code ".$Fields2["KeyCode"]."\r\n";
					}
					$EmailMessage .= "\r\nIf we have an incorrect graduation date for you, or you have already returned your keys, please let us know and we will update our records. Thanks!\r\n\r\nSincerely,\r\nECE Key Tracking System";

					//echo $EmailMessage."\r\n";
    				mail($EmailTo, "**IMPORTANT** ECE Borrowed Keys", $EmailMessage, "From: keys@apps.ece.rutgers.edu\r\nReply-To: kevin.wine@rutgers.edu\r\nCC: kevin.wine@rutgers.edu");

	    			//Write a change record to note the fact that a key reminder email was sent.
        			UpdateField($Fields1["UID"], '0', $CurrentTime, "Notes", "Two-week key reminder email sent to ".$EmailTo.".");

					WriteLog("Two-week email sent to ".$Fields1["FirstName"]." ".$Fields1["LastName"]." at ".$EmailTo." for ".$SQLResult2->num_rows." keys.");
				}

				//One week out.
				if ( ($Fields1["DepartureDateUTC"] >= $OneWeek1) && ($Fields1["DepartureDateUTC"] < $OneWeek2) && ($EmailTo != "") ) {

					//Setup the email message.
					$EmailMessage = "Dear ".$Fields1["FirstName"]." ".$Fields1["LastName"].",\r\n\r\nIt appears that you will be graduating very soon. Please be sure to return any keys that you have borrowed before you finally depart. Based on our records, you have borrowed at least the following keys:\r\n\r\n";
					while ($Fields2 = $SQLResult2->fetch_assoc()) {
						$EmailMessage .= " Room ".$Fields2["RoomNumber"].", Key Code ".$Fields2["KeyCode"]."\r\n";
					}
					$EmailMessage .= "\r\nIf we have an incorrect graduation date for you, or you have already returned your keys, please let us know and we will update our records. Thanks!\r\n\r\nSincerely,\r\nECE Key Tracking System";

					//echo $EmailMessage."\r\n";
    				mail($EmailTo, "**URGENT** ECE Borrowed Keys", $EmailMessage, "From: keys@apps.ece.rutgers.edu\r\nReply-To: kevin.wine@rutgers.edu\r\nCC: kevin.wine@rutgers.edu");

	    			//Write a change record to note the fact that a key reminder email was sent.
        			UpdateField($Fields1["UID"], '0', $CurrentTime, "Notes", "One-week key reminder email sent to ".$EmailTo.".");

					WriteLog("One-week email sent to ".$Fields1["FirstName"]." ".$Fields1["LastName"]." at ".$EmailTo." for ".$SQLResult2->num_rows." keys.");
				}
			}
        }
    }


CloseSQL:
	//Done with sql server.
   $mysqli->close();

	WriteLog("Daily Key Processing Done");
	exit();

    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Keys/KeysDailyProcess.log"); 
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


<!--
Executes a cron job every night at 3AM (actually executes at 4AM):
0 3 * * * /opt/local/bin/php /www/custom/eceapps/Keys/KeysDailyProcess.php

crontab -e to edit the cron jobs
crontab -l to display cron jobs
VI editor. Press I to insert text. ESC to go back to cursor mode. Press R to overwrite. :w to write. :x to exit.
d to delete a row, when in normal mode.
x to delete a character. Put curson on character, push x, when in normal mode.

Can't use relative paths in cron php files.

-->
