<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

    //Set for the title of the page.
    $title = "ECE Apps - Capstone Config";
	$Menu = "Capstone";
    $UserDoingUpdateAccessRole = "";

    //If the user is already logged in, their ID will have been saved in the current session.
    if (empty($_SESSION['netid'])) {
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">You have to login first in order to access this page.</DIV>";
		goto SendTailer;
	}
    //Moved here to after the login check to avoid error messages printing by PHP.
    $UserDoingUpdateNetID = $_SESSION['netid'];

	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli -> connect_errno) {
		WriteLog("Failed to connect to SQL server: ".$mysqli->connect_error);
		require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">Failed to connect to the MySQL database server: ".$mysqli->connect_error."</DIV>";
		goto SendTailer;
	}


    //Lookup the user to get their access role.
    $SQLQuery = "SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$UserDoingUpdateNetID."' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    	require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">Database query for user ".$UserDoingUpdateNetID." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows < 1) {
   		WriteLog("User with NetID ".$UserDoingUpdateNetID." not found.");
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">User ".$UserDoingUpdateNetID." not found.</DIV>";
		goto CloseSQL;
	}

    //Extract the user's role.
	$Fields = $SQLResult->fetch_row();
    $UserDoingUpdateUID = $Fields[0];
    $UserDoingUpdateAccessRole = $Fields[1];

	//This has to go out after the user is authenticated and their role is determined, because the menus are based on role.
	require('../template/head.php');

	//This is needed later to figure out if the student has graduated or not.
	$CurrentTime = time();

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Page content starts here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }


	//Check all the form fields and store the new value.
	$Errors = FALSE;
	$Errors |= ValidateField(TRUE, "replytonetid", "ReplyTo NetID", "0123456789abcdefghijklmnopqrstuvwxyz", 1, 25, FALSE);
	$Errors |= ValidateField(TRUE, "newordernetid", "New Order NetID", "0123456789abcdefghijklmnopqrstuvwxyz", 1, 25, FALSE);
	$Errors |= ValidateField(TRUE, "approvedordernetid", "Approved Order NetID", "0123456789abcdefghijklmnopqrstuvwxyz", 1, 25, FALSE);
	$Errors |= ValidateField(TRUE, "springexpodate", "Spring Expo Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "fallexpodate", "Fall Expo Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "springformeddate", "Spring Teams Formed Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "fallformeddate", "Fall Teams Formed Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "springround1date", "Spring Round 1 Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "fallround1date", "Fall Round 1 Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "springround2date", "Spring Round 2 Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "fallround2date", "Fall Round 2 Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "springround3date", "Spring Round 3 Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "fallround3date", "Fall Round 3 Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(FALSE, "email1", "Approval Email Text", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?\t\n\r ", 0, 2000, FALSE);
	$Errors |= ValidateField(FALSE, "email2", "Denial Email Text", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?\t\n\r ", 0, 2000, FALSE);
	$Errors |= ValidateField(FALSE, "email3", "Pending Email Text", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?\t\n\r ", 0, 2000, FALSE);
	$Errors |= ValidateField(FALSE, "newnotes", "New Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?\t\n\r ", 0, 2000, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}


	//The Reply-To: NetID has to be converted into a UserID.
	//Look for all active users with the NetID.
	$SQLQuery = "SELECT Users.UID, User.UserStatus FROM Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
			FROM Users) AS User
		ON Users.UID=User.UID
		WHERE NetID='".$_POST["replytonetid"]."' AND UserStatus='A';";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for netID ".$_POST["replytonetid"]." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows == 0) {
   		WriteLog("User with NetID ".$_POST["replytonetid"]." not found.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User with NetID ".$_POST["replytonetid"]." not found.</DIV>";
		goto CloseSQL;
	}

	if ($SQLResult->num_rows > 1) {
   		WriteLog($SQLResult->num_rows." users with NetID ".$_POST["replytonetid"]." found.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Multiple users with NetID ".$_POST["replytonetid"]." found.</DIV>";
		goto CloseSQL;
	}
	$Fields = $SQLResult->fetch_row();
	$ReplyToUserID = $Fields[0];


	//NetID has to be converted into a UserID.
	$SQLQuery = "SELECT Users.UID, User.UserStatus FROM Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
			FROM Users) AS User
		ON Users.UID=User.UID
		WHERE NetID='".$_POST["newordernetid"]."' AND UserStatus='A';";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for netID ".$_POST["newordernetid"]." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows == 0) {
   		WriteLog("User with NetID ".$_POST["newordernetid"]." not found.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User with NetID ".$_POST["newordernetid"]." not found.</DIV>";
		goto CloseSQL;
	}

	if ($SQLResult->num_rows > 1) {
   		WriteLog($SQLResult->num_rows." users with NetID ".$_POST["newordernetid"]." found.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Multiple users with NetID ".$_POST["newordernetid"]." found.</DIV>";
		goto CloseSQL;
	}
	$Fields = $SQLResult->fetch_row();
	$NewOrderUserID = $Fields[0];


	//NetID has to be converted into a UserID.
	$SQLQuery = "SELECT Users.UID, User.UserStatus FROM Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
			FROM Users) AS User
		ON Users.UID=User.UID
		WHERE NetID='".$_POST["approvedordernetid"]."' AND UserStatus='A';";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for netID ".$_POST["approvedordernetid"]." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows == 0) {
   		WriteLog("User with NetID ".$_POST["approvedordernetid"]." not found.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User with NetID ".$_POST["approvedordernetid"]." not found.</DIV>";
		goto CloseSQL;
	}

	if ($SQLResult->num_rows > 1) {
   		WriteLog($SQLResult->num_rows." users with NetID ".$_POST["approvedordernetid"]." found.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Multiple users with NetID ".$_POST["approvedordernetid"]." found.</DIV>";
		goto CloseSQL;
	}
	$Fields = $SQLResult->fetch_row();
	$ApprovedOrderUserID = $Fields[0];


	//Convert the M/D/Y dates back to UTC format.
	$Date = explode("/", $_POST["springexpodate"]);
	$SpringExpoDateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));
	$Date = explode("/", $_POST["fallexpodate"]);
	$FallExpoDateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));

	$Date = explode("/", $_POST["springformeddate"]);
	$SpringFormedDateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));
	$Date = explode("/", $_POST["fallformeddate"]);
	$FallFormedDateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));

	$Date = explode("/", $_POST["springround1date"]);
	$SpringRound1DateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));
	$Date = explode("/", $_POST["fallround1date"]);
	$FallRound1DateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));

	$Date = explode("/", $_POST["springround2date"]);
	$SpringRound2DateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));
	$Date = explode("/", $_POST["fallround2date"]);
	$FallRound2DateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));

	$Date = explode("/", $_POST["springround3date"]);
	$SpringRound3DateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));
	$Date = explode("/", $_POST["fallround3date"]);
	$FallRound3DateUTC = strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));

	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "ReplyToEmailUID", $ReplyToUserID);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "NewOrderEmailUID", $NewOrderUserID);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "ApprovedOrderEmailUID", $ApprovedOrderUserID);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "SpringExpoDateUTC", $SpringExpoDateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "FallExpoDateUTC", $FallExpoDateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "SpringFormedDateUTC", $SpringFormedDateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "FallFormedDateUTC", $FallFormedDateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "SpringRound1DateUTC", $SpringRound1DateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "FallRound1DateUTC", $FallRound1DateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "SpringRound2DateUTC", $SpringRound2DateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "FallRound2DateUTC", $FallRound2DateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "SpringRound3DateUTC", $SpringRound3DateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "FallRound3DateUTC", $FallRound3DateUTC);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "Email1", $_POST["email1"]);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "Email2", $_POST["email2"]);
	$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "Email3", $_POST["email3"]);


	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField(0, $UserDoingUpdateUID, $CurrentTime, "Notes", $_POST["newnotes"]);

	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">Configuration successfully updated.</DIV>";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Configuration not changed.</DIV>";

CloseSQL:
    //Done with SQL server.
    $mysqli->close();

    echo "</DIV>";
    echo "</DIV>";

SendTailer:
    require('../template/footer.html');
    require('../template/foot.php');

?>


<?php
    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Capstone/CapstoneProcessConfig.log"); 
    }

	//Validates a form field.
	function ValidateField($RequiredFlag, $FormField, $DisplayName, $ValidChars, $MinValue, $MaxValue, $NumberFlag) {

	    if (isset($_POST[$FormField])) {
			$FieldValue = $_POST[$FormField];
			$FieldLength = strlen($FieldValue);
		}
		else {
			$FieldValue = "";
			$FieldLength = 0;
		}

		//If the field is blank and not required, do nothing. If it is blank and required, that is an error.
		//This situation comes up because un-checked check boxes and blank fields do not appear in the form data.
		if ($FieldLength == 0) {
			if ($RequiredFlag == TRUE) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." must be provided.</DIV>";
				return TRUE;
			}
			else
				return FALSE;
		}

		//Check for illegal characters.
		if ( ($CharPosition = strspn($FieldValue, $ValidChars)) < $FieldLength) {
			$CharPosition++;
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Illegal character in ".$DisplayName." field \"".$FieldValue."\" at position ".$CharPosition."</DIV>";
			return TRUE;
		}

		if ($NumberFlag == TRUE) {

			//Check maximum length.
			if ($FieldLength > 10) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is ".$FieldLength." digits long, and maximum allowed is 10 digits.</DIV>";
				return TRUE;
			}

			//Check maximum value.
			if ( ($MaxValue > 0) && ($FieldValue > $MaxValue) ) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is beyond maximum value of ".$MaxValue."</DIV>";
				return TRUE;
			}

			//Check minimum value.
			if ( ($MinValue > 0) && ($FieldValue < $MinValue) ) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is below minimum value of ".$MinValue."</DIV>";
				return TRUE;
			}
		}
		else {

			//Check minimum length.
			if ($FieldLength < $MinValue) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is ".$FieldLength." characters long, and minimum required is ".$MinValue." characters.</DIV>";
				return TRUE;
			}

			//Check maximum length.
			if ($FieldLength > $MaxValue) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is ".$FieldLength." characters long, and maximum allowed is ".$MaxValue." characters.</DIV>";
				return TRUE;
			}
		}

		return FALSE;
    }


	//Stores the field to a name/value record in the change record table.
	//Returns TRUE if the field was created or changed, FALSE if it wasn't or if there was an error.
	function UpdateField($UserBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM CapstoneConfig WHERE ID=".$UserBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve change record: ".$mysqli->error."</DIV>";
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
		$SQLQuery = "INSERT INTO CapstoneConfig (
			ID,
			CreateUID,
			CreateTime,
			Field,
			Value
		) VALUES (
			'".$UserBeingUpdated."',
			'".$UserDoingUpdate."',
			'".$TimeStamp."',
			'".$FieldName."',
			'".mysqli_real_escape_string($mysqli, $FieldValue)."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to add change record: ".$mysqli->error."</DIV>";
			return FALSE;
		}

		return TRUE;
    }


	//Function to retrieve a specified field for a specified user.
	// This is here because there are some field checks that require the prior value of the field.
    function RetrieveField($Table, $UID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
	        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve field from database: ".$mysqli->error."</DIV>";
		    return "";
	    }

        //Not all fields are defined for every user, so it is not an error for the field to not be found.
	    if ($SQLResult->num_rows == 1) {
        	$Fields = $SQLResult->fetch_row();
            return $Fields[0];
        }
        else {
            return "";
        }
    }
?>
