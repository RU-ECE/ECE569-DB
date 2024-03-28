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
    $Title = "Users";
	$Menu = "Users";
    $UserDoingUpdateAccessRole = "";

    //If the user is already logged in, their ID will have been saved in the current session.
    if (empty($_SESSION['netid'])) {
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You have to login first in order to access this page.</DIV>";
		goto SendTailer;
	}
    //Moved here to after the login check to avoid error messages printing by PHP.
    $NetID = $_SESSION['netid'];


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


	//Check all the form fields and store the new value.
	$Errors = FALSE;
	$Errors |= ValidateField(TRUE, "studenttype", "Student Type", "UMWPN", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "ruid", "RUID", "1234567890", 9, 9, FALSE);
	$Errors |= ValidateField(TRUE, "firstname", "First Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 1, 50, FALSE);
	$Errors |= ValidateField(FALSE, "middlename", "Middle Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 0, 50, FALSE);
	$Errors |= ValidateField(TRUE, "lastname", "Last Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 1, 50, FALSE);
	$Errors |= ValidateField(FALSE, "gender", "Gender", "MFZ", 1, 1, FALSE);
	$Errors |= ValidateField(FALSE, "phone1", "Phone 1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 50, FALSE);
	$Errors |= ValidateField(TRUE, "preferredemail", "Preferred Email", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_~+.@", 5, 80, FALSE);
	$Errors |= ValidateField(TRUE, "departdate", "Graduation Date", "0123456789/", 8, 10, FALSE);
	$Errors |= ValidateField(TRUE, "major", "Major", "0123456789", 3, 3, FALSE);
	$Errors |= ValidateField(FALSE, "track", "Track", "AB", 1, 1, FALSE);
	$Errors |= ValidateField(FALSE, "newnotes", "Additional Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?\r\n ", 0, 2000, FALSE);
	$Errors |= ValidateDate("departdate", "Graduation Date");

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}


	//Make sure there isn't already a student with this NetID and/or RUID.
	$SQLQuery = "SELECT Users.UID, Student.NetID, Student.RUID, Student.FirstName, Student.LastName FROM Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName
			FROM Users) AS Student
		ON Users.UID=Student.UID
		WHERE (NetID='".$NetID."' AND NetID<>'') OR (RUID='".mysqli_real_escape_string($mysqli, $_POST["ruid"])."' AND RUID<>'') OR ((FirstName='".mysqli_real_escape_string($mysqli, $_POST["firstname"])."') AND (LastName='".mysqli_real_escape_string($mysqli, $_POST["lastname"])."'));";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("Error ".$mysqli->error." checking for existing user:".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error checking if student exists: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Make sure no student(s) were found.
	if (mysqli_num_rows($SQLResult) != 0) {
		$Fields = $SQLResult->fetch_assoc();
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new student. Existing student found with NetID: ".$Fields["NetID"].", RUID: ".$Fields["RUID"].", Name: ".$Fields["FirstName"]." ".$Fields["LastName"]."</DIV>\r\n";
		goto CloseSQL;
	}

	//Any derived field values can be calculated now.
	//Dates need to be blank if not filled in. User could have erased a date, which then needs to create a change record with a blank date.
	$GraduationUTC = ConvertDate("departdate");


	$SQLQuery = "INSERT INTO Users (
		CreateTime,
		MissingFlag,
		SessionID
	) VALUES (
		'".$CurrentTime."',
		'F',
		'0'
	);";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("Error ".$mysqli->error." adding user:".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new user: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Get the ID of this user. I tried to combine this with the query above but that doesn't work.
	$SQLQuery = "SELECT LAST_INSERT_ID();";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("Error ".$mysqli->error." getting ID of new user:".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new user: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}
	$Fields = $SQLResult->fetch_row();
	$UserBeingUpdatedUID = $Fields[0];


	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "NetID", $NetID);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "RUID", $_POST["ruid"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "UserStatus", "A");
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "AccessRole", "D");
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "StudentType", $_POST["studenttype"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "Major", $_POST["major"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "Track", $_POST["track"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "Gender", $_POST["gender"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "DepartureDateUTC", $GraduationUTC);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "FirstName", $_POST["firstname"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "MiddleName", $_POST["middlename"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "LastName", $_POST["lastname"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "Phone1", $_POST["phone1"]);
	UpdateField($UserBeingUpdatedUID, 0, $CurrentTime, "PreferredEmail", $_POST["preferredemail"]);

	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Notes", $_POST["newnotes"]);

	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">Student successfully created. Create a new <A href=\"../SPN/SPNReqStudentCreateForm.php?reqid=0\">SPN Request</A>.";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersStudentProcessNew.log"); 
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

	//Validates a m/d/y date field. Returns TRUE if there was an error. Date could be blank, which is not an error.
	function ValidateDate($FormField, $DisplayName) {


	    if (empty($_POST[$FormField]))
			return FALSE;

		if ($_POST[$FormField] == "")
			return FALSE;

		//Chop up the date. Only one seperator type can be specified.
		$Date = explode("/", $_POST[$FormField]);

		//Let php do the date validation.
		if (checkdate($Date[0], $Date[1], $Date[2]) == FALSE) {
			WriteLog("ValidateDate(): Date is invalid ".$DisplayName);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is invalid.</DIV>";
			return TRUE;
		}

		//We still have to check to make sure the year is reasonable.
		if ($Date[2] < 2000) {
			WriteLog("ValidateDate(): Year is too far back ".$Date[2]);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Year .".$Date[2]." is too far back.</DIV>";
			return TRUE;
		}

		return FALSE;
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
CREATE TABLE `TestUsers` (
  `UID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT,
  `MissingFlag` VARCHAR(1),
  `SessionID` VARCHAR(50)
);

CREATE TABLE `TestUserRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);
-->