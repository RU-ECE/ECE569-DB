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

	$DayOfMonth = date("j", $CurrentTime);
//$DayOfMonth = 1;

	//Log the start of the script.
	WriteLog("Daily Users Processing");
	//echo "Daily Users Processing\r\n";


	//If this is the first of the month, send an absense report reminder to all non-exempt staff.
	if ($DayOfMonth == 1) {

		//Get a list of all the non-exempt staff.
		$SQLQuery = "SELECT Users.UID, User.FirstName, User.LastName, User.OfficialEmail, User.PreferredEmail, User.NonExemptCheck, User.UserStatus FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
					(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
					(SELECT Value FROM UserRecords WHERE Field='OfficialEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS OfficialEmail,
					(SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PreferredEmail,
					(SELECT Value FROM UserRecords WHERE Field='NonExemptCheck' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NonExemptCheck,
					(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
				FROM Users) AS User
			ON Users.UID=User.UID
			WHERE NonExemptCheck='T' AND UserStatus='A';";
		$SQLResult1 = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			//echo "Failed to get users list: ".$mysqli->error."\r\n";
			goto CloseSQL;
		}


		//Go through all the users found.
		if ($SQLResult1->num_rows > 0) {

			WriteLog($SQLResult1->num_rows." non-exempt users found.");
			while ($Fields1 = $SQLResult1->fetch_assoc()) {

				//Emails are sent on the first of the month.

				//Setup the sending email address(s).
				if ($Fields1["OfficialEmail"] == $Fields1["PreferredEmail"])
					$EmailTo = $Fields1["PreferredEmail"];
				else if ( ($Fields1["OfficialEmail"] == "") && ($Fields1["PreferredEmail"] != "") )
					$EmailTo = $Fields1["PreferredEmail"];
				else if ( ($Fields1["OfficialEmail"] != "") && ($Fields1["PreferredEmail"] == "") )
					$EmailTo = $Fields1["OfficialEmail"];
				else if ( ($Fields1["OfficialEmail"] != "") && ($Fields1["PreferredEmail"] != "") )
					$EmailTo = $Fields1["PreferredEmail"];
				else {
					$EmailTo = "";
					WriteLog("Non-exempt user but no email address for ".$Fields1["FirstName"]." ".$Fields1["LastName"]);
				}


				if ($EmailTo != "") {

					//We need a random validation key, so if the user clicks on the link we can make sure it's them without making them login.
					$ValidationKey = GenerateSessionID(30);

					//Setup the email message.
					$EmailMessage = "Dear ".$Fields1["FirstName"]." ".$Fields1["LastName"].",\r\n\r\nPlease review your absense report on your my.rutgers portal. You are encouraged to use your vacation/PH/AL days throughout the year. Click here to acknowledge that you have reviewed the report:\r\n\r\nhttps://apps.ece.rutgers.edu/Users/UsersProcessAck.php?userid=".$Fields1["UID"]."&key=".$ValidationKey."\r\n\r\nSincerely,\r\n ECE Apps";

					//echo $EmailMessage."\r\n";
    				mail($EmailTo, "ECE Apps Monthly Absence Report", $EmailMessage, "From: users@apps.ece.rutgers.edu\r\nReply-To: ah860@soe.rutgers.edu\r\nCC: ah860@soe.rutgers.edu");

					//Save the validation string in the User's SessionID record. This assumes external users will never get emails like this.
					$SQLQuery = "UPDATE Users SET SessionID='".$ValidationKey."' WHERE UID=".$Fields1["UID"].";";
					$SQLResult = $mysqli->query($SQLQuery);
					if ($mysqli->error)
						WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);

					//And set a flag to note that this user has to respond to the email.
					UpdateField($Fields1["UID"], $Fields1["UID"], $CurrentTime, "AbsenceReview", "F");

					WriteLog("Absence report reminder email sent to ".$Fields1["FirstName"]." ".$Fields1["LastName"]." at ".$EmailTo.".");
				}
			}
		}
	}


	//If this is not the first of the month, then send reminder emails to any user that has not clicked yet.
	if ($DayOfMonth != 1) {

		//Get a list of all the non-exempt staff that hasn't clicked on the reminder link yet.
		$SQLQuery = "SELECT Users.UID, Users.SessionID, User.FirstName, User.LastName, User.OfficialEmail, User.PreferredEmail, User.NonExemptCheck, User.AbsenceReview, User.UserStatus FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
					(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
					(SELECT Value FROM UserRecords WHERE Field='OfficialEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS OfficialEmail,
					(SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PreferredEmail,
					(SELECT Value FROM UserRecords WHERE Field='NonExemptCheck' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NonExemptCheck,
					(SELECT Value FROM UserRecords WHERE Field='AbsenceReview' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS AbsenceReview,
					(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
				FROM Users) AS User
			ON Users.UID=User.UID
			WHERE NonExemptCheck='T' AND UserStatus='A' AND AbsenceReview='F';";
		$SQLResult1 = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			//echo "Failed to get users list: ".$mysqli->error."\r\n";
			goto CloseSQL;
		}


		//Go through all the users found.
		if ($SQLResult1->num_rows > 0) {

			WriteLog($SQLResult1->num_rows." non-exempt users found.");
			while ($Fields1 = $SQLResult1->fetch_assoc()) {

				//Setup the sending email address(s).
				if ($Fields1["OfficialEmail"] == $Fields1["PreferredEmail"])
					$EmailTo = $Fields1["PreferredEmail"];
				else if ( ($Fields1["OfficialEmail"] == "") && ($Fields1["PreferredEmail"] != "") )
					$EmailTo = $Fields1["PreferredEmail"];
				else if ( ($Fields1["OfficialEmail"] != "") && ($Fields1["PreferredEmail"] == "") )
					$EmailTo = $Fields1["OfficialEmail"];
				else if ( ($Fields1["OfficialEmail"] != "") && ($Fields1["PreferredEmail"] != "") )
					$EmailTo = $Fields1["PreferredEmail"];
				else {
					$EmailTo = "";
					WriteLog("Non-exempt user but no email address for ".$Fields1["FirstName"]." ".$Fields1["LastName"]);
				}


				if ($EmailTo != "") {

					//Setup the email message.
					$EmailMessage = "Dear ".$Fields1["FirstName"]." ".$Fields1["LastName"].",\r\n\r\nThis is a reminder email to check your absense report. Click here to acknowledge you have reviewed the report:\r\n\r\nhttps://apps.ece.rutgers.edu/Users/UsersProcessAck.php?userid=".$Fields1["UID"]."&key=".$Fields1["SessionID"]."\r\n\r\nSincerely,\r\n ECE Apps";

					//echo $EmailMessage."\r\n";
    				mail($EmailTo, "ECE Apps Monthly Absence Report", $EmailMessage, "From: users@apps.ece.rutgers.edu\r\nReply-To: ah860@soe.rutgers.edu\r\nCC: ah860@soe.rutgers.edu");

					WriteLog("Absence report reminder email sent to ".$Fields1["FirstName"]." ".$Fields1["LastName"]." at ".$EmailTo.".");
				}
			}
		}
	}

CloseSQL:
	//Done with sql server.
   $mysqli->close();

	WriteLog("Daily User Processing Done");
	exit();

    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersDailyProcess.log"); 
    }

	//Generates a random string, for use as a SessionID.
    function GenerateSessionID($Length) {

        $PossibleChars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $RandomString = "";

        for($i = 0; $i < $Length; $i++)
            $RandomString .= substr($PossibleChars, rand(0, 61), 1);

        return $RandomString;
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
0 3 * * * /opt/local/bin/php /www/custom/eceapps/Users/UsersDailyProcess.php

crontab -e to edit the cron jobs
crontab -l to display cron jobs
VI editor. Press i to insert text. ESC to go back to cursor mode. Press R to overwrite. :w to write. :x to exit.

Can't use relative paths in cron php files.



INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (46, 51, UNIX_TIMESTAMP(), 'NonExemptCheck', 'T');
INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (49, 51, UNIX_TIMESTAMP(), 'NonExemptCheck', 'T');
INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1364, 51, UNIX_TIMESTAMP(), 'NonExemptCheck', 'T');
INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1741, 51, UNIX_TIMESTAMP(), 'NonExemptCheck', 'T');
INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (50, 51, UNIX_TIMESTAMP(), 'NonExemptCheck', 'T');
INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (52, 51, UNIX_TIMESTAMP(), 'NonExemptCheck', 'T');


-->
