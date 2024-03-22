<?php
	session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Set for the title of the page.
    $Title = "SPN";
	$Menu = "SPNs";
    $UserDoingUpdateAccessRole = "";

    //If the user is already logged in, their ID will have been saved in the current session.
    if (empty($_SESSION['netid'])) {
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You have to login first in order to access this page.</DIV>";
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
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to connect to the MySQL database server: ".$mysqli->connect_error."</DIV>";
		goto SendTailer;
	}

    //Lookup the user to get their access role.
    $SQLQuery = "SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$UserDoingUpdateNetID."' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    	require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for user ".$UserDoingUpdateNetID." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows < 1) {
   		WriteLog("User with NetID ".$UserDoingUpdateNetID." not found.");
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User ".$UserDoingUpdateNetID." not found.</DIV>";
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

	//Page content starts here..
	echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit an SPN.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }


	//Validate all the form fields.
	$Errors = FALSE;
	$Errors |= ValidateFormField(TRUE, "type", "Type", "UGSI", 1, 1, FALSE);
	$Errors |= ValidateFormField(TRUE, "semester", "Semester", "SMF", 1, 1, FALSE);
	$Errors |= ValidateFormField(TRUE, "year", "Year", "0123456789", 4, 4, FALSE);
	$Errors |= ValidateFormField(TRUE, "year", "Year", "0123456789", 4, 4, FALSE);
	$Errors |= ValidateFormField(TRUE, "spns", "SPN List", "0123456789\r\n", 0, 10000, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	$Type = $_POST["type"];
	$Semester = $_POST["semester"];
	$Year = $_POST["year"];
	$Course = $_POST["course"];
	$Section = $_POST["section"];
	$SPNs = $_POST["spns"];

	//Go through all the rows in the file.
	$RowsRead = 0;
	$SPNsCreated = 0;
	$SPNErrors = 0;

	$Separator = "\r\n";
	$SPN = strtok($SPNs, $Separator);

	while ($SPN !== false) {

		$RowsRead++;

		//Progress report.
		if ( (($RowsRead) % 250) == 0) {
			WriteLog($RowsRead." SPNs read, ".$SPNsCreated." SPN created, ".$SPNErrors." errors.");
			echo("<P>".$RowsRead." SPNs read, ".$SPNsCreated." SPNs created, ".$SPNErrors." errors.</P>");
		}

		//Store the new SPN to the table.
		$SQLQuery = "INSERT INTO SPNs (
			CreateTime,
			Type,
			Semester,
			Year,
			Course,
			Section,
			SPN
		) VALUES (
			'".$CurrentTime."',
			'".$Type."',
			'".$Semester."',
			'".$Year."',
			'".$Course."',
			'".$Section."',
			'".$SPN."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." adding SPN:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error adding SPN: ".$mysqli->error."</DIV>";
			$SPNErrors++;
			$SPN = strtok( $Separator );
			continue;
		}

		//Get the ID of the new SPN. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." adding SPN:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error get SID of new SPN: ".$mysqli->error."</DIV>";
			$SPNErrors++;
			$SPN = strtok( $Separator );
			continue;
		}
		$Fields = $SQLResult->fetch_row();
		$SPNBeingCreatedID = $Fields[0];

		//Save all the fields in the records table.
		$ChangeFlag = FALSE;
		$ChangeFlag |= UpdateField($SPNBeingCreatedID, $UserDoingUpdateUID, $CurrentTime, "Status", "A");

		//If something was changed, then log another new SPN.
		if ($ChangeFlag)
			$SPNsCreated++;

		//Get the next line.
		$SPN = strtok( $Separator );
	}

	//Import report.
	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">";
	echo $RowsRead." lines processed.<BR>";
	echo $SPNsCreated." new SPNs created in database.<BR>";
	echo $SPNErrors." lines with import errors were not saved.<BR>";
	echo "</DIV>";

CloseSQL:
	$mysqli->close();

SendTailer:
    echo "</DIV>";
	echo "</DIV>";

	require('../template/footer.html');
	require('../template/foot.php');
?>


<?php
    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNProcessBatch.log"); 
    }


	//Validates a CSV field.
    function ValidateCSVField($FieldName, $CSVValue, $ValidChars, $MinLength, $MaxLength) {
	 
		global $mysqli;


		//This is needed in several places..
		$FieldLength = strlen($CSVValue);

		//If the field is blank there is no point in saving it.
		if ($FieldLength <= 0)
			return FALSE;

		//Check minimum length.
		if ($FieldLength < $MinLength) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$FieldName." is ".$FieldLength." characters long, and minimum required is ".$MinLength." characters.</DIV>";
			return TRUE;
		}

		//Check maximum length.
		if ($FieldLength > $MaxLength) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$FieldName." is ".$FieldLength." characters long, and maximum allowed is ".$MaxLength." characters.</DIV>";
			return TRUE;
		}

		//Check for illegal characters.
		if ( ($CharPosition = strspn($CSVValue, $ValidChars) ) < $FieldLength) {
			$CharPosition++;
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Illegal character in ".$FieldName." field \"".$CSVValue."\" at position ".$CharPosition."</DIV>";
			return TRUE;
		}

		return FALSE;
    }


	//Validates a form field.
	function ValidateFormField($RequiredFlag, $FormField, $DisplayName, $ValidChars, $MinValue, $MaxValue, $NumberFlag) {

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
	// Returns TRUE if the record was stored, FALSE if it wasn't or there was an error.
	// The return value is used to determine if there was a change to the field.
	function UpdateField($SPNBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM SPNRecords WHERE ID=".$SPNBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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
		$SQLQuery = "INSERT INTO SPNRecords (
			ID,
			CreateUID,
			CreateTime,
			Field,
			Value
		) VALUES (
			'".$SPNBeingUpdated."',
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
?>
