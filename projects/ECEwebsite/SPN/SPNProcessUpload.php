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
	$Menu = "SPN Upload";
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

	//Access Control.
    //Staff only for this version of the page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit an SPN.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to create or edit an SPN using this page.</DIV>";
		goto CloseSQL;
    }

	//Check to make sure the file uploaded without errors.
	// Error #1 is "file too large" - bigger than the "upload_max_filesize" in php.ini
	if (!isset($_FILES["users"]) || $_FILES["users"]["error"] != 0) {
		WriteLog("Error ".$_FILES["users"]["error"]." uploading SPN file".$_FILES["users"]["name"]);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$_FILES["users"]["error"]." uploading SPN file ".$_FILES["users"]["name"]."</DIV>";
		goto CloseSQL;
	}

	//Setup an array of the allowed MIME types.
	//When I upload a .csv file, the MIME type apparently is application/vnd.ms-excel, not text/csv
//	$AllowedTypes = array("csv" => "application/vnd.ms-excel");
	$AllowedTypes = array("csv" => "text/csv");

	//Save useful strings.
	$FileName = $_FILES["users"]["name"];
	$FileType = $_FILES["users"]["type"];
	$FileSize = $_FILES["users"]["size"];
	$TempFile = $_FILES["users"]["tmp_name"];

	//Log the upload information, in case this stops working..
	WriteLog("File uploaded, FileName=".$FileName.", FileType=".$FileType.", FileSize=".$FileSize.", TempFile=".$TempFile);


	//Check the file extension.
	$FileExtension = pathinfo($FileName, PATHINFO_EXTENSION);
	if (!array_key_exists($FileExtension, $AllowedTypes)) {
		WriteLog("File type ".$FileExtension." not allowed.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">File type ".$FileExtension." not allowed. File type must be .csv.</DIV>";
		goto CloseSQL;
	}

	//Check the max file size - currently allowing up to 2MB
	if ($FileSize > 2097152) {
		WriteLog("File is too big, ".$FileSize." bytes.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Maximum file size is 2MB. Your file is ".$FileSize." bytes</DIV>";
		goto CloseSQL;
	}

	if ($FileSize <= 0) {
		WriteLog("File is empty, ".$FileSize." bytes.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Your file is empty!</DIV>";
		goto CloseSQL;
	}

	//Verify the MIME type of the file.
	if (!in_array($FileType, $AllowedTypes)) {
 		WriteLog("MIME type ".$FileType." is not allowed.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Incorrect file type of ".$FileType." File must be .csv.</DIV>";
		goto CloseSQL;
	}

	//With the uploaded file still in temporary storage, attempt to insert all the rows in a new SQL table.
	//Open the temporary file for reading.        
	if ( ($hInputFile = fopen($TempFile, "r")) === FALSE) {
		WriteLog("fopen failed on file ".$TempFile);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to process input file.</DIV>";
		goto CloseSQL;
	}


	//Validate all the form fields.
	$Errors = FALSE;
	$Errors |= ValidateFormField(TRUE, "type", "Type", "UGSI", 1, 1, FALSE);
	$Errors |= ValidateFormField(TRUE, "semester", "Semester", "SMF", 1, 1, FALSE);
	$Errors |= ValidateFormField(TRUE, "year", "Year", "0123456789", 4, 4, FALSE);
	$Errors |= ValidateFormField(FALSE, "preview", "Preview Mode", "on", 0, 4, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	$Type = $_POST["type"];
	$Semester = $_POST["semester"];
	$Year = $_POST["year"];
	$Course = "";
	$Section = "";
	$SPN = "";
	$PreviewCheck = "";
	if (isset($_POST["preview"]))
	    if ($_POST["preview"] != "")
		    $PreviewCheck = "checked";


	//Go through all the rows in the file.
	$RowsRead = 0;
	$SPNsCreated = 0;
	$SPNErrors = 0;
	while (($CSVFields = fgetcsv($hInputFile, 1000, ",")) !== FALSE) {

		$RowsRead++;

		//Progress report.
		if ( (($RowsRead) % 250) == 0) {
			WriteLog($RowsRead." SPNs read, ".$SPNsCreated." SPN created, ".$SPNErrors." errors.");
			echo("<P>".$RowsRead." SPNs read, ".$SPNsCreated." SPNs created, ".$SPNErrors." errors.</P>");
		}

		//Validate all the incoming CSV fields.
		$Errors = FALSE;


		//School
		$Errors |= ValidateCSVField("School", $CSVFields[0], "1234567890", 0, 2);

		//Program
		$Errors |= ValidateCSVField("Program", $CSVFields[1], "1234567890", 0, 3);

		//Course Number
		$Errors |= ValidateCSVField("Course", $CSVFields[2], "1234567890", 0, 3);

		//Section Number
		$Errors |= ValidateCSVField("Section", $CSVFields[4], "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 0, 2);

		//SPN
		$Errors |= ValidateCSVField("SPN", $CSVFields[7], "1234567890", 0, 6);

		//If any errors were found with this SPN print out the row.
		if ($Errors) {
			echo "<P>Error in Row ".$RowsRead.":  School: ".$CSVFields[0].", Program: ".$CSVFields[1].", Course: ".$CSVFields[2].", Section: ".$CSVFields[4].", SPN: ".$CSVFields[7]."</P>";
			$SPNErrors++;
			continue;
		}

		//Skip blank rows.
		if ( ($CSVFields[0] == "") && ($CSVFields[1] == "") && ($CSVFields[2] == "") && ($CSVFields[3] == "") && ($CSVFields[4] == "") && ($CSVFields[5] == "") && ($CSVFields[6] == "") && ($CSVFields[7] == "") )
			continue;

		//If this is a row with the course information, capture it.
		if ($CSVFields[0] == "14") {
			$Course = $CSVFields[2];
			$Section = str_pad($CSVFields[4], 2, "0", STR_PAD_LEFT);
			//There is no SPN on these lines, so on to the next one.
			continue;
		}

		//Has to be an SPN line, so grab it.
		//During the csv file processing, the leading zeros get cut off the SPN, so fix that.
		$SPN = str_pad($CSVFields[7], 6, "0", STR_PAD_LEFT);

		//If we are in preview mode, don't write anything to the tables.
		if ($PreviewCheck != "")
			continue;

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
			continue;
		}

		//Get the ID of this user. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." adding SPN:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error get SID of new SPN: ".$mysqli->error."</DIV>";
			$SPNErrors++;
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
	}

	//Import report.
	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">";
	echo $RowsRead." lines processed from CSV file <STRONG>".$FileName."</STRONG><BR>";
	echo $SPNsCreated." new SPNs created in database.<BR>";
	echo $SPNErrors." lines with import errors were not saved.<BR>";
	echo "</DIV>";


CloseFile:
	fclose($hInputFile);

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNProcessUpload.log"); 
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
