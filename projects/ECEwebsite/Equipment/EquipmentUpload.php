<?php
	//CLEANED UP 8/28/2021 AND NEVER TESTED. SAVED THE PRIOR WORKING VERSION IF NEEDED.

	session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

    //Set for the title of the page.
    $title = "ECE Apps - Staff Portal";
	$Menu = "Equipment";
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
/*
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
*/
$UserDoingUpdateUID = 0; 
$UserDoingUpdateAccessRole = "S";

	//This has to go out after the user is authenticated and their role is determined, because the menus are based on role.
	require('../template/head.php');

	//This is needed because I gave up on the mySQL time functions, and I want to use the same timestamp for all the records that are
	// created here so there is some way to identify all the changes that were made in a particular batch.
	$CurrentTime = time();

	//Page content starts here..
	echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

	//THIS NEEDS TO BE EXTRACTED FROM THE USER SOMEHOW!?
	$CreateUID = 0;

	//Check to make sure the file uploaded without errors.
	// Error #1 is "file too large" - bigger than the "upload_max_filesize" in php.ini
	if (!isset($_FILES["equip"]) || $_FILES["equip"]["error"] != 0) {
		WriteLog("Error ".$_FILES["equip"]["error"]." uploading the equipment data file".$_FILES["equip"]["name"]);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$_FILES["equip"]["error"]." uploading the equipment data file ".$_FILES["equip"]["name"]."</DIV>";
		goto CloseSQL;
	}

	//Setup an array of the allowed MIME types.
	//When I upload a .csv file, the MIME type apparently is application/vnd.ms-excel, not text/csv
	$AllowedTypes = array("csv" => "application/vnd.ms-excel");

	//Save useful strings.
	$FileName = $_FILES["equip"]["name"];
	$FileType = $_FILES["equip"]["type"];
	$FileSize = $_FILES["equip"]["size"];
	$TempFile = $_FILES["equip"]["tmp_name"];

	//Log the upload information, in case this stops working..
	//WriteLog("File uploaded, FileName=".$FileName.", FileType=".$FileType.", FileSize=".$FileSize.", TempFile=".$TempFile);


	//Check the file extension.
	$Extension = pathinfo($FileName, PATHINFO_EXTENSION);
	if (!array_key_exists($Extension, $AllowedTypes)) {
		WriteLog("File type ".$Extension." not allowed.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">File type .".$Extension." not allowed. File type must be .csv.</DIV>";
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
	if ( ($hInputFile = fopen($TempFile, "r")) === false) {
		WriteLog("fopen failed on file ".$TempFile);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to process input file.</DIV>";
		goto CloseSQL;
	}

	//The first line has to be read for the column headers.
	$CSVFields = fgetcsv($hInputFile, 1000, ",");


	//Get the index of all the relevant columns in the .csv file.
	if ( ($ECETagIndex = array_search("ECETag", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"ECETag\" (ECE Tag Number) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($RUTagIndex = array_search("RUTag", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"RUTag\" (RU Tag Number) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($StatusIndex = array_search("Status", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Status\" (Status) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($MakeIndex = array_search("Make", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Make\" (Manufacturer) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($ModelIndex = array_search("Model", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Model\" (Model Name) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($SerialIndex = array_search("Serial", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Serial\" (Serial Number) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($TypeIndex = array_search("Type", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Type\" (Equipment Type) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($DescriptionIndex = array_search("Description", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Description\" (Description) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($AcquireDateIndex = array_search("AcquireDate", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"AcquireDate\" (Date Acquired) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($LocationIndex = array_search("Location", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Location\" (Location) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($NotesIndex = array_search("Notes", $CSVFields)) === false) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Notes\" (Notes) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}

	//Go through all the rows in the file.
	$EquipmentRead = 0;
	$EquipmentCreated = 0;
	$EquipmentErrors = 0;
	while (($CSVFields = fgetcsv($hInputFile, 1000, ",")) !== FALSE) {

		$EquipmentRead++;

		//Progress report.
		if ( (($EquipmentRead) % 500) == 0) {
			WriteLog($EquipmentRead." equipment read, ".$EquipmentCreated." equipment created, ".$EquipmentErrors." errors.");
			//WriteLog("RUID=".$CSVFields[$RUIDIndex].", Name=".$CSVFields[$FullNameIndex].", Email=".$CSVFields[$EmailIndex]);
		}

		//Validate all the fields.
		$Errors = 0;

		//ECE Tag Number.
		$Errors |= ValidateField("ECETag", $CSVFields[$ECETagIndex], "1234567890/-. ", 1, 20);
		$Errors |= ValidateField("RUTag", $CSVFields[$RUTagIndex], "1234567890/-. ", 1, 20);

		//Status.
		if ($CSVFields[$StatusIndex] == "Active")
			$EquipmentStatus = "A";
		else if ($CSVFields[$StatusIndex] == "Loaned")
			$EquipmentStatus = "L";
		else if ($CSVFields[$StatusIndex] == "Broken")
			$EquipmentStatus = "B";
		else if ($CSVFields[$StatusIndex] == "Surplused")
			$EquipmentStatus = "S";
		else if ($CSVFields[$StatusIndex] == "Missing")
			$EquipmentStatus = "M";
		else if ($CSVFields[$StatusIndex] == "")
			$EquipmentStatus = "U";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown Equipment Status: ".$CSVFields[$StatusIndex]."</DIV>";
			$Errors = TRUE;
			continue;
		}
		$Errors |= ValidateField("Status", $EquipmentStatus, "AMBSLU", 0, 1);

		//Make
		$Errors |= ValidateField("Make", $CSVFields[$MakeIndex], "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 30);

		//Model
		$Errors |= ValidateField("Model", $CSVFields[$ModelIndex], "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 30);

		//Serial
		$Errors |= ValidateField("Serial", $CSVFields[$SerialIndex], "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 30);

		//Type
		$Errors |= ValidateField("Type", $CSVFields[$TypeIndex], "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 40);

		//Description
		$Errors |= ValidateField("Description", $CSVFields[$DescriptionIndex], "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 50);

		//Acquire Date
		$Errors |= ValidateField("AcquireDate", $CSVFields[$AcquireDateIndex], "0123456789/", 0, 10);

		//Dates need to be blank if not filled in. User could have erased a date, which then needs to create a change record with a blank date.
		$AcquireDateUTC = ConvertDate($CSVFields[$AcquireDateIndex]);



		//If any errors were found with this piece of equipment print its ECE tag so the user knows what to go and fix.
		if ($Errors) {
			echo "<P>Equipment tag ".$CSVFields[$ECETagIndex]." had errors, so none of its data was saved.</P>";
			$EquipmentErrors++;
			continue;
		}

		//Add this equipment to the Equipment table with fields that either don't change or don't need to be logged.
//THERE IS NO WAY OF KNOWING AT THIS POINT WHETHER ANY FIELDS WILL ACTUALLY BE WRITTEN, YET AN ENTRY IS MADE IN USERS TABLE... HUMMMM.
		$SQLQuery = "INSERT INTO Equip (
			CreateTime
		) VALUES (
			'".$CurrentTime."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." adding equipment:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error adding equipment: ".$mysqli->error."</DIV>";
			$EquipmentErrors++;
			continue;
		}

		//Get the ID of this equipment. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." adding equipment:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error getting ID of new equipment: ".$mysqli->error."</DIV>";
			$EquipmentErrors++;
			continue;
		}
		$Fields = $SQLResult->fetch_row();
		$EID = $Fields[0];


		//Store all the fields. This has to be done after all the fields have been validated, to prevent partial records.
		UpdateField($EID, $CreateUID, $CurrentTime, "ECETag", $CSVFields[$ECETagIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "RUTag", $CSVFields[$RUTagIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "Status", $EquipmentStatus);
		UpdateField($EID, $CreateUID, $CurrentTime, "Make", $CSVFields[$MakeIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "Model", $CSVFields[$ModelIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "Serial", $CSVFields[$SerialIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "Type", $CSVFields[$TypeIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "Description", $CSVFields[$DescriptionIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "AcquireDateUTC", $AcquireDateUTC);
		UpdateField($EID, $CreateUID, $CurrentTime, "Location", $CSVFields[$LocationIndex]);
		UpdateField($EID, $CreateUID, $CurrentTime, "Notes", $CSVFields[$NotesIndex]);

		$EquipmentCreated++;
	}

	//Import report.
	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">";
	echo "Equipment read in from CSV file <STRONG>".$FileName."</STRONG>:".$EquipmentRead."<BR>";
	echo "New equipment created in database:".$EquipmentCreated." <BR>";
	echo "Equipment with import errors that were not processed:".$EquipmentErrors."<BR>";
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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Equipment/EquipmentUpload.log"); 
    }


	//Validates a CSV field.
    function ValidateField($FieldName, $CSVValue, $ValidChars, $MinLength, $MaxLength) {
	 
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


 	//Stores the field to a name/value record in the change record table.
	// Returns TRUE if the record was stored, FALSE if it wasn't or there was an error.
	// The return value is used to determine if there was a change to the field.
	function UpdateField($EquipmentID, $UserID, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM EquipRecords WHERE ID=".$EquipmentID." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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
		$SQLQuery = "INSERT INTO EquipRecords (
			ID,
			CreateUID,
			CreateTime,
			Field,
			Value
		) VALUES (
			'".$EquipmentID."',
			'".$UserID."',
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


	//Converts a m/d/y date in a form field to UTC. Returns blank string if there is no date. Assumes fields were previously validated!
	function ConvertDate($FormField) {
	    
		if (empty($_POST[$FormField]))
			return "";

		if ($_POST[$FormField] == "")
			return "";

		//Chop up the date. Only one seperator type can be specified.
		$Date = explode("/", $_POST[$FormField]);

		return strval(mktime(0, 0, 0, $Date[0], $Date[1], $Date[2]));
	}
?>

<!--
CREATE TABLE `Equip` (
  `EID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT
);

CREATE TABLE `EquipRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

-->
