<?php
    //session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Set for the title of the page.
    $Title = "Users";
    $Menu = "Users";
    $UserDoingUpdateAccessRole = "";

	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli -> connect_errno) {
		WriteLog("Failed to connect to SQL server: ".$mysqli->connect_error);
		require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to connect to the MySQL database server: ".$mysqli->connect_error."</DIV>";
		goto SendTailer;
	}

	//This has to go out after the user is authenticated and their role is determined, because the menus are based on role.
	require('../template/head.php');

	//This is needed later to figure out if the student has graduated or not.
	$CurrentTime = time();

    //Page content starts here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

	//There is no authentication check, because the validation key is used as the check.


	//If this is an update to an existing user, the UID of that user comes through in a hidden field.
	$UserBeingUpdatedUID = "";
    if (isset($_GET["userid"]))
		if (!ValidateField(FALSE, "userid", "User ID", "1234567890", 0, 10, FALSE))
		    $UserBeingUpdatedUID = $_GET["userid"];


	$Errors = FALSE;
	$Errors |= ValidateField(FALSE, "key", "Validation Key", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890", 0, 30, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	//Make sure the validation key submitted in this form matches the key saved in the user's record.
	$SQLQuery = "SELECT Users.UID, Users.SessionID FROM Users WHERE UID='".$UserBeingUpdatedUID."' AND SessionID='".$_GET["key"]."';";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("Error ".$mysqli->error." checking for validation key:".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error checking validation key: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Make sure one and only 1 record was found.
	if (mysqli_num_rows($SQLResult) != 1) {
		$Fields = $SQLResult->fetch_assoc();
		WriteLog("Validation key does not match: ".$_GET["key"]." for UserID: ".$UserBeingUpdatedUID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Validation key does not match.</DIV>\r\n";
		goto CloseSQL;
	}

	//Save the acknowledgement, so the user won't get any more reminders.
	UpdateField($UserBeingUpdatedUID, $UserBeingUpdatedUID, $CurrentTime, "AbsenceReview", "T");

	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">Absence report reminder successfully acknowledged.</DIV>";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersProcessAck.log"); 
    }

	//Validates a form field.
	function ValidateField($RequiredFlag, $FormField, $DisplayName, $ValidChars, $MinValue, $MaxValue, $NumberFlag) {

	    if (isset($_GET[$FormField])) {
			$FieldValue = $_GET[$FormField];
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

		$SQLQuery = "SELECT Value FROM UserRecords WHERE ID=".$UserBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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
			'".$UserBeingUpdated."',
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
