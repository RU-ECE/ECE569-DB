<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

    //Set for the title of the page.
    $title = "ECE Apps - Staff Portal";
	$Menu = "Teams";
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

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Page content starts here..
    echo "<DIV id=\"content\">";
    echo "<DIV CLASS=\"container\">";


	//Access Control.
    //Staff only for this version of the page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit team.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to access this page.</DIV>";
		goto CloseSQL;
    }


	//If this is an update to an existing team, the Team ID of that team comes through in a hidden field.
//FIELD NEEDS TO BE VALIDATED!!
	$SpnBeingUpdatedSID = "";
    if (isset($_POST["spnid"]))
	    if ($_POST["spnid"] != "")
		    $SpnBeingUpdatedSID = $_POST["spnid"];


    $FirstName = "";
    $LastName = "";
    $RUID = "";
    $Email = "";
    $StartDate = "";
    $EndDate = "";
    $EmployerName = "";
    $EmployerAddress = "";
    $Location = "";
    $JobTitle = "";
    $JobDetail = "";
    $JobHours = "";
    $Check = "";
    $Semester = "";


	//Check all the form fields and store the new values.
	//teamseason, teamyear, and teamnumber are only filled in when a staff member is entering a new team.
	$Errors = FALSE;

	$Errors |= ValidateField(TRUE, "firstname", "First Name", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 1, 30, FALSE);
	$Errors |= ValidateField(TRUE, "lastname", "Last Name", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 1, 30, FALSE);
	$Errors |= ValidateField(TRUE, "ruid", "RUID", "1234567890.", 0, 10, FALSE);
	$Errors |= ValidateField(TRUE, "email", "Email", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "startdate", "Intern start date", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "enddate", "Intern end date", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "employername", "Employer name", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 1, 30, FALSE);
	$Errors |= ValidateField(TRUE, "employeraddress", "Employer address", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "location", "Location", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "jobtitle", "Job title", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "jobdetail", "Job detail", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 1, 200, FALSE);
	$Errors |= ValidateField(TRUE, "jobhours", "Job hours", "1234567890.", 0, 10, FALSE);
	$Errors |= ValidateField(TRUE, "semester", "Semester", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 1, 30, FALSE);



	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	//Any derived field values can be calculated now.
	$Check = "";
	if (isset($_POST["check"]))
		if ($_POST["check"] == "checked")
			$Check = "T";


	//This has to be done because contactid may not be present in the form data, which creates a warning later..
	$ContactID = "";
	if (isset($_POST["contactid"]))
		$ContactID = $_POST["contactid"];

	//Is it safe to assume the ID of a new Advisor is valid??


    //This has to be done to prevent lots of undefined index errors (which I guess could be ignored as another solution).
    if (isset($_POST["statuscheck"]))
        $StatusCheck = $_POST["statuscheck"];

    if (isset($_POST["statussearch"]))
        $StatusSearch = $_POST["statussearch"];

    if (isset($_POST["teamcheck"]))
        $TeamCheck = $_POST["teamcheck"];

    if (isset($_POST["teamyearsearch"]))
        $TeamYearSearch = $_POST["teamyearsearch"];

    if (isset($_POST["teamnumbersearch"]))
        $TeamNumberSearch = $_POST["teamnumbersearch"];

    if (isset($_POST["teamseasonsearch"]))
        $TeamSeasonSearch = $_POST["teamseasonsearch"];

    if (isset($_POST["advisorcheck"]))
        $AdvisorCheck = $_POST["advisorcheck"];

    if (isset($_POST["advisorsearch"]))
        $AdvisorSearch = $_POST["advisorsearch"];

    if (isset($_POST["lockercheck"]))
        $LockerCheck = $_POST["lockercheck"];

    if (isset($_POST["sort1"]))
	    $Sort1 = $_POST["sort1"];

    if (isset($_POST["sort2"]))
	    $Sort2 = $_POST["sort2"];


	//If this is a new team then we need to get the next team ID.
    if ($SpnBeingUpdatedSID == "") {

		$SQLQuery = "INSERT INTO Spns (
			CreateTime
		) VALUES (
			'".$CurrentTime."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." creating spn:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new spn request: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Get the ID of this team. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." getting ID of new spn:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new spn request: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$SpnBeingUpdatedSID = $Fields[0];
	}

	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "FirstName", $_POST["firstname"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "LastName", $_POST["lastname"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "RUID", $_POST["ruid"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "Email", $_POST["email"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "StartDate", $_POST["startdate"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "EndDate", $_POST["enddate"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "EmployerName", $_POST["employername"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "EmployerAddress", $_POST["employeraddress"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "Location", $_POST["location"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "JobTitle", $_POST["jobtitle"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "JobDetail", $_POST["jobdetail"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "JobHours", $_POST["jobhours"]);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "Check", $Check);
	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "Semester", $_POST["semester"]);


	$uploaddir = '/www/custom/eceapps/Spns/uploads/';
	$uploadresume = $uploaddir.basename($_FILES['resume']['name']);
	$uploadoffer = $uploaddir.basename($_FILES['offer']['name']);

	if (move_uploaded_file($_FILES['resume']['tmp_name'], $uploadresume)) {
		echo "resume is valid, and was successfully uploaded.\n";
	} else {
		echo "Fail to upload resume!\n";
	}

	if (move_uploaded_file($_FILES['offer']['tmp_name'], $uploadoffer)) {
		echo "offer letter is valid, and was successfully uploaded.\n";
	} else {
		echo "Fail to upload offer letter!\n";
	}


//THESE ARE IN THE Teams TABLE AND CURRENTLY CAN'T BE CHANGED..
//	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "TeamNumber", $_POST["teamnumber"]);
//	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "TeamYear", $_POST["teamyear"]);
//	$ChangeFlag |= UpdateField($SpnBeingUpdatedSID, $UserDoingUpdateUID, $CurrentTime, "TeamSeason", $_POST["teamseason"]);

//WHAT TO DO ABOUT SAVING THE ADVISOR ID?

//AND HOW DO WE HANDLE REMOVING/ADDING STUDENTS TO THE TEAM?


	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"SpnsStaffCreateForm.php?spnid=".$SpnBeingUpdatedSID."\">SPN</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"TeamsStaffCreateForm.php?teamid=".$SpnBeingUpdatedSID."\">SPN</A>.";

	echo " Create a <A HREF=\"https://apps.ece.rutgers.edu/Spns/SpnsStaffCreateForm.php\">new spn request</A>.";
    echo " Back to <A HREF=\"TeamsStaffCreateList.php\">previous search</A>.</DIV>\r\n";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Teams/SpnsStaffProcessForm.log"); 
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
	function UpdateField($IDBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		if ($FieldName == "")
			return FALSE;

		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM SpnRecords WHERE ID=".$IDBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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
		$SQLQuery = "INSERT INTO SpnRecords (
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

foreach ($_GET as $key => $value) {
    echo "Field ".htmlspecialchars($key)." is ".htmlspecialchars($value)."<br>";
}

-->