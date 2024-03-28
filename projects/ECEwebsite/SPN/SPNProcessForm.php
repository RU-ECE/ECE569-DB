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
    echo "<DIV id=\"content\">";
    echo "<DIV CLASS=\"container\">";


	//Access Control.
    //Staff only for this version of the page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit an SPN.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to create or edit an SPN using this page.</DIV>";
		goto CloseSQL;
    }


	//Check all the form fields and store the new values.
	$Errors = FALSE;
	$Errors |= ValidateField(FALSE, "spnid", "ID", "0123456789", 1, 10, FALSE);
	$Errors |= ValidateField(TRUE, "status", "Status", "AUI", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "semester", "Semester", "SMF", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "year", "Year", "0123456789", 1, 4, FALSE);
	$Errors |= ValidateField(TRUE, "course", "Course Number", "0123456789", 1, 3, FALSE);
	$Errors |= ValidateField(TRUE, "section", "Course Section", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 1, 2, FALSE);
	$Errors |= ValidateField(TRUE, "spn", "SPN", "0123456789", 1, 6, FALSE);
	$Errors |= ValidateField(FALSE, "netid", "NetID", "abcdefghijklmnopqrstuvwxyz0123456789", 0, 25, FALSE);
	$Errors |= ValidateField(FALSE, "newnotes", "New Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 2000, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}


	//Any derived field values can be calculated now.

	//We need to lookup the UserID based on the NetID.
	//We need to convert the NetID into a UserID. At the same time, we can get the requesters name and email address which may be needed later.
	$UserID = "";
	if ($_POST["netid"] != "") {

		//Look for all active users with the NetID.
		$SQLQuery = "SELECT Users.UID, User.NetID, User.UserStatus FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
					(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
				FROM Users) AS User
			ON Users.UID=User.UID
			WHERE NetID='".$_POST["netid"]."';";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for netID ".$_POST["netid"]." has failed: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Check if user was found.
		if ($SQLResult->num_rows != 1) {
   			WriteLog("User with NetID ".$_POST["netid"]." not found ".$SQLResult->num_rows);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User with NetID ".$_POST["netid"]." not found.</DIV>";
			goto CloseSQL;
		}

		$Fields = $SQLResult->fetch_row();
		$UserID = $Fields[0];
	}


	//If this is an update to an existing SPN, the SPNID comes through in a hidden field.
	$SPNID = "";
    if (isset($_POST["spnid"]))
		if (!ValidateField(FALSE, "spnid", "SPN ID", "0123456789", 1, 10, FALSE))
		    $SPNID = $_POST["spnid"];


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_POST["statuscheck"]))
		if (!ValidateField(FALSE, "statuscheck", "Status Check", "checked", 0, 7, FALSE))
			$StatusCheck = $_POST["statuscheck"];

    $StatusSearch = "";
    if (isset($_POST["statussearch"]))
		if (!ValidateField(FALSE, "statussearch", "Status Search", "AUI", 0, 1, FALSE))
			$StatusSearch = $_POST["statussearch"];

    $TypeCheck = "";
    if (isset($_POST["typecheck"]))
		if (!ValidateField(FALSE, "typecheck", "Type Check", "checked", 0, 7, FALSE))
			$TypeCheck = $_POST["typecheck"];

    $TypeSearch = "";
    if (isset($_POST["typesearch"]))
		if (!ValidateField(FALSE, "typesearch", "Type Search", "UGSI", 0, 1, FALSE))
			$TypeSearch = $_POST["typesearch"];

	$SemesterCheck = "";
    if (isset($_POST["semestercheck"]))
		if (!ValidateField(FALSE, "semestercheck", "Semester Check", "checked", 0, 7, FALSE))
			$SemesterCheck = $_POST["semestercheck"];

    $SemesterSearch = "";
    if (isset($_POST["semestersearch"]))
		if (!ValidateField(FALSE, "semestersearch", "Semester Search", "SMF", 1, 1, FALSE))
			$SemesterSearch = $_POST["semestersearch"];

    $YearSearch = "";
    if (isset($_POST["yearsearch"]))
		if (!ValidateField(FALSE, "yearsearch", "Year Search", "0123456789", 1, 4, FALSE))
			$YearSearch = $_POST["yearsearch"];

	$CourseCheck = "";
    if (isset($_POST["coursecheck"]))
		if (!ValidateField(FALSE, "coursecheck", "Course Check", "checked", 0, 7, FALSE))
			$CourseCheck = $_POST["coursecheck"];

	$CourseSearch = "";
    if (isset($_POST["coursesearch"]))
		if (!ValidateField(FALSE, "coursesearch", "Course Search", "0123456789", 1, 3, FALSE))
			$CourseSearch = $_POST["coursesearch"];

	$SectionCheck = "";
    if (isset($_POST["sectioncheck"]))
		if (!ValidateField(FALSE, "sectioncheck", "Section Check", "checked", 0, 7, FALSE))
			$SectionCheck = $_POST["sectioncheck"];

	$SectionSearch = "";
    if (isset($_POST["sectionsearch"]))
		if (!ValidateField(FALSE, "sectionsearch", "Section Search", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 1, 2, FALSE))
			$SectionSearch = $_POST["sectionsearch"];

    $SPNCheck = "";
    if (isset($_POST["spncheck"]))
		if (!ValidateField(FALSE, "spncheck", "SPN Check", "checked", 0, 7, FALSE))
			$SPNCheck = $_POST["spncheck"];

    $SPNSearch = "";
    if (isset($_POST["spnsearch"]))
		if (!ValidateField(FALSE, "spnsearch", "SPN Search", "0123456789", 0, 6, FALSE))
			$SPNSearch = $_POST["spnsearch"];

    $NetIDCheck = "";
    if (isset($_POST["netidcheck"]))
		if (!ValidateField(FALSE, "netidcheck", "NetID Check", "checked", 0, 7, FALSE))
	       $NetIDCheck = $_POST["netidcheck"];

    $NetIDSearch = "";
    if (isset($_POST["netidsearch"]))
		if (!ValidateField(FALSE, "netidsearch", "NetID Search", "abcdefghijklmnopqrstuvwxyz0123456789", 0, 6, FALSE))
	       $NetIDSearch = $_POST["netidsearch"];

    $Sort1 = "";
    if (isset($_POST["sort1"]))
		if (!ValidateField(FALSE, "sort1", "First Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
			$Sort1 = $_POST["sort1"];

    $Sort2 = "";
    if (isset($_POST["sort2"]))
		if (!ValidateField(FALSE, "sort2", "Second Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
			$Sort2 = $_POST["sort2"];


	//If this is a new SPN create a new record.
    if ($SPNID == "") {

		$SQLQuery = "INSERT INTO `SPNs` (
			CreateTime,
			Type,
			Semester,
			Year,
			Course,
			Section,
			SPN
		) VALUES (
			'".$CurrentTime."',
			'".$_POST["type"]."',
			'".$_POST["semester"]."',
			'".$_POST["year"]."',
			'".$_POST["course"]."',
			'".str_pad($_POST["section"], 2, "0", STR_PAD_LEFT)."',
			'".str_pad($_POST["spn"], 6, "0", STR_PAD_LEFT)."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." creating SPN:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new SPN: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Get the ID of this SPN. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." getting ID of new SPN:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new SPN: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$SPNID = $Fields[0];
	}

	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField("SPNRecords", $SPNID, $UserDoingUpdateUID, $CurrentTime, "Status", $_POST["status"]);
	$ChangeFlag |= UpdateField("SPNRecords", $SPNID, $UserDoingUpdateUID, $CurrentTime, "UserID", $UserID);

	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField("SPNRecords", $SPNID, $UserDoingUpdateUID, $CurrentTime, "Notes",  mysqli_real_escape_string($mysqli, $_POST["newnotes"]));


	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"SPNCreateForm.php?spnid=".$SPNID."\">SPN ".$SPNID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"SPNCreateForm.php?spnid=".$SPNID."\">SPN ".$SPNID."</A>.";

    echo " Back to <A HREF=\"SPNCreateList.php?statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&semestercheck=".$SemesterCheck."&semestersearch=".urlencode($SemesterSearch)."&yearsearch=".urlencode($YearSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionearch=".urlencode($SectionSearch)."&spncheck=".$SPNCheck."&spnsearch=".urlencode($SPNSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.</DIV>\r\n";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNProcessForm.log"); 
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
	function UpdateField($Table, $IDBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$IDBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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
		$SQLQuery = "INSERT INTO ".$Table." (
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
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to add change record: ".$mysqli->error."</DIV>";
			return FALSE;
		}

		return TRUE;
    }
?>
