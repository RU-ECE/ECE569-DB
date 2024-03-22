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

	//If this is an update to an existing user, the UID of that user comes through in a hidden field.
	$UserBeingUpdatedUID = "";
    if (isset($_POST["userid"]))
		if (!ValidateField(FALSE, "userid", "User ID", "1234567890", 0, 10, FALSE))
		    $UserBeingUpdatedUID = $_POST["userid"];


    //Make sure user is allowed to view this page.
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit userID ".$UserBeingUpdatedUID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_POST["statuscheck"]))
		if (!ValidateField(FALSE, "statuscheck", "Status Check", "checked", 0, 7, FALSE))
			$StatusCheck = $_POST["statuscheck"];

	$StatusSearch = "";
    if (isset($_POST["statussearch"]))
		if (!ValidateField(FALSE, "statussearch", "Status Search", "AGLSRED", 0, 10, FALSE))
			$StatusSearch = $_POST["statussearch"];

    $NetIDCheck = "";
    if (isset($_POST["netidcheck"]))
		if (!ValidateField(FALSE, "netidcheck", "NetID Check", "checked", 0, 7, FALSE))
		    $NetIDCheck = $_POST["netidcheck"];

	$NetIDSearch = "";
    if (isset($_POST["netidsearch"]))
		if (!ValidateField(FALSE, "netidsearch", "NetID Search", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 0, 20, FALSE))
	   		$NetIDSearch = $_POST["netidsearch"];

    $RUIDCheck = "";
    if (isset($_POST["ruidcheck"]))
		if (!ValidateField(FALSE, "ruidcheck", "RUID Check", "checked", 0, 7, FALSE))
		    $RUIDCheck = $_POST["ruidcheck"];

	$RUIDSearch = "";
    if (isset($_POST["ruidsearch"]))
		if (!ValidateField(FALSE, "ruidsearch", "RUID Search", "0123456789", 0, 10, FALSE))
	   		$RUIDSearch = $_POST["ruidsearch"];

    $NameCheck = "";
    if (isset($_POST["namecheck"]))
		if (!ValidateField(FALSE, "namecheck", "Name Check", "checked", 0, 7, FALSE))
		    $NameCheck = $_POST["namecheck"];

	$NameSearch = "";
    if (isset($_POST["namesearch"]))
		if (!ValidateField(FALSE, "namesearch", "Name Search", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 0, 50, FALSE))
			$NameSearch = $_POST["namesearch"];

    $GenderCheck = "";
    if (isset($_POST["gendercheck"]))
		if (!ValidateField(FALSE, "gendercheck", "Gender Check", "checked", 0, 7, FALSE))
		    $GenderCheck = $_POST["gendercheck"];

	$GenderSearch = "";
    if (isset($_POST["gendersearch"]))
		if (!ValidateField(FALSE, "gendersearch", "Gender Search", "MFZ", 0, 10, FALSE))
			$GenderSearch = $_POST["gendersearch"];

    $ClassCheck = "";
    if (isset($_POST["classcheck"]))
		if (!ValidateField(FALSE, "classcheck", "Class Check", "checked", 0, 7, FALSE))
		    $ClassCheck = $_POST["classcheck"];

	$ClassSearch = "";
    if (isset($_POST["classsearch"]))
		if (!ValidateField(FALSE, "classsearch", "Class Search", "0123456789", 0, 10, FALSE))
			$ClassSearch = $_POST["classsearch"];

    $StudentTypeCheck = "";
    if (isset($_POST["studenttypecheck"]))
		if (!ValidateField(FALSE, "studenttypecheck", "Student Type Check", "checked", 0, 7, FALSE))
		    $StudentTypeCheck = $_POST["studenttypecheck"];

	$StudentTypeSearch = "";
    if (isset($_POST["studenttypesearch"]))
		if (!ValidateField(FALSE, "studenttypesearch", "Student Type Search", "UMWPN", 0, 10, FALSE))
			$StudentTypeSearch = $_POST["studenttypesearch"];

    $EmployeeTypeCheck = "";
    if (isset($_POST["employeetypecheck"]))
		if (!ValidateField(FALSE, "employeetypecheck", "Employee Type Check", "checked", 0, 7, FALSE))
			$EmployeeTypeCheck = $_POST["employeetypecheck"];

	$EmployeeTypeSearch = "";
    if (isset($_POST["employeetypesearch"]))
		if (!ValidateField(FALSE, "employeetypesearch", "Employee Type Search", "SFANTGWPERH", 0, 10, FALSE))
			$EmployeeTypeSearch = $_POST["employeetypesearch"];

    $MajorCheck = "";
    if (isset($_POST["majorcheck"]))
		if (!ValidateField(FALSE, "majorcheck", "Major Check", "checked", 0, 7, FALSE))
			$MajorCheck = $_POST["majorcheck"];

	$MajorSearch = "";
    if (isset($_POST["majorsearch"]))
		if (!ValidateField(FALSE, "majorsearch", "Major Search", "0123456789", 0, 10, FALSE))
			$MajorSearch = $_POST["majorsearch"];

    $TrackCheck = "";
    if (isset($_POST["trackcheck"]))
		if (!ValidateField(FALSE, "trackcheck", "Track Check", "checked", 0, 7, FALSE))
			$TrackCheck = $_POST["trackcheck"];

	$TrackSearch = "";
    if (isset($_POST["tracksearch"]))
		if (!ValidateField(FALSE, "tracksearch", "Track Search", "AB", 0, 10, FALSE))
			$TrackSearch = $_POST["tracksearch"];

    $JudgeCheck = "";
    if (isset($_POST["judgecheck"]))
		if (!ValidateField(FALSE, "judgecheck", "Judge Check", "checked", 0, 7, FALSE))
			$JudgeCheck = $_POST["judgecheck"];

    $AdvisorCheck = "";
    if (isset($_POST["advisorcheck"]))
		if (!ValidateField(FALSE, "advisorcheck", "Advisor Check", "checked", 0, 7, FALSE))
			$AdvisorCheck = $_POST["advisorcheck"];

    $MissingCheck = "";
    if (isset($_POST["missingcheck"]))
		if (!ValidateField(FALSE, "missingcheck", "Missing Check", "checked", 0, 7, FALSE))
			$MissingCheck = $_POST["missingcheck"];

    $Sort1 = "";
    if (isset($_POST["sort1"]))
		if (!ValidateField(FALSE, "sort1", "First Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 0, 50, FALSE))
		    $Sort1 = $_POST["sort1"];

    $Sort2 = "";
    if (isset($_POST["sort2"]))
		if (!ValidateField(FALSE, "sort2", "Second Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 0, 50, FALSE))
		    $Sort2 = $_POST["sort2"];


	//Check all the form fields and store the new value.
	$Errors = FALSE;
	$Errors |= ValidateField(TRUE, "userstatus", "User Status", "AGLSRED", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "accessrole", "Access Role", "SFDAO", 1, 1, FALSE);
	$Errors |= ValidateField(FALSE, "netid", "NetID", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890", 0, 20, FALSE);
	$Errors |= ValidateField(FALSE, "ruid", "RUID", "1234567890", 0, 20, FALSE);
	$Errors |= ValidateField(FALSE, "major", "Major", "0123456789", 0, 5, FALSE);
	$Errors |= ValidateField(FALSE, "prefix", "Prefix", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 0, 10, FALSE);
	$Errors |= ValidateField(TRUE, "firstname", "First Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 1, 50, FALSE);
	$Errors |= ValidateField(FALSE, "middlename", "Middle Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 0, 50, FALSE);
	$Errors |= ValidateField(TRUE, "lastname", "Last Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 1, 50, FALSE);
	$Errors |= ValidateField(FALSE, "suffix", "Suffix", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-',. ", 0, 10, FALSE);
	$Errors |= ValidateField(FALSE, "gender", "Gender", "MFZ", 0, 1, FALSE);
	$Errors |= ValidateField(FALSE, "officialemail", "Official Email", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_~+.@", 5, 80, FALSE);
	$Errors |= ValidateField(FALSE, "alternateemail", "Alternate Email", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_~+.@", 5, 80, FALSE);
	$Errors |= ValidateField(FALSE, "preferredemail", "Preferred Email", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_~+.@", 5, 80, FALSE);
	$Errors |= ValidateField(FALSE, "phone1", "Phone 1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "phone2", "Phone 2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "street1", "Street 1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#/-'`.,()? ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "street2", "Street 2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#/-'`.,()? ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "city", "City", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "state", "State", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 30, FALSE);
	$Errors |= ValidateField(FALSE, "zip", "Zip/Postal Code", "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890- ", 0, 10, FALSE);
	$Errors |= ValidateField(FALSE, "country", "Country", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 30, FALSE);
	$Errors |= ValidateField(FALSE, "studenttype", "Student Type", "UMWPN", 0, 1, FALSE);
	$Errors |= ValidateField(FALSE, "track", "Track", "AB", 0, 1, FALSE);
	$Errors |= ValidateField(FALSE, "departdate", "Graduation or Departure Date", "0123456789/", 1, 10, FALSE);
	$Errors |= ValidateField(FALSE, "trainingdate", "Safety Training Date", "0123456789/", 1, 10, FALSE);
	$Errors |= ValidateField(FALSE, "handsondate", "Hands-On Training Date", "0123456789/", 1, 10, FALSE);
	$Errors |= ValidateField(FALSE, "advisor1uid", "Advisor 1", "1234567890", 0, 5, FALSE);		
	$Errors |= ValidateField(FALSE, "advisor2uid", "Advisor 2", "1234567890", 0, 5, FALSE);		
	$Errors |= ValidateField(FALSE, "njresident", "In-State Resident", "checked", 0, 7, FALSE);
	$Errors |= ValidateField(FALSE, "credits", "Credits","1234567890", 0, 5, FALSE);		
	$Errors |= ValidateField(FALSE, "citizen", "Citizen", "UFPDZ", 0, 1, FALSE);
	$Errors |= ValidateField(FALSE, "visa", "Visa", "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890", 0, 2, FALSE);
	$Errors |= ValidateField(FALSE, "employeetype", "Employee Type", "SFANTGWPERHZ", 0, 1, FALSE);
	$Errors |= ValidateField(FALSE, "title", "Title", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.-,() ", 0, 80, FALSE);
	$Errors |= ValidateField(FALSE, "office", "Office", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-,() ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "company", "Company Name", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890.-',() ", 0, 100, FALSE);
	$Errors |= ValidateField(FALSE, "judgecheck", "Potential Judge", "checked", 0, 7, FALSE);
	$Errors |= ValidateField(FALSE, "advisorcheck", "Capstone Advisor", "checked", 0, 7, FALSE);
	$Errors |= ValidateField(FALSE, "password1", "Password", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?", 8, 80, FALSE);
	$Errors |= ValidateField(FALSE, "password2", "Retype Password", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?", 8, 80, FALSE);
	$Errors |= ValidateField(FALSE, "newnotes", "Additional Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/?\r\n ", 0, 2000, FALSE);

	$Errors |= ValidateDate("departdate", "Graduation or Departure Date");
	$Errors |= ValidateDate("trainingdate", "Safety Training Date");
	$Errors |= ValidateDate("handsondate", "Hands-on Training Date");


	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}


	//Make sure there isn't already a user with either this NetID, RUID, or name.
	if ($UserBeingUpdatedUID == "") {
		$SQLQuery = "SELECT Users.UID, User.NetID, User.RUID, User.FirstName, User.LastName FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
					(SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
					(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
					(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName
				FROM Users) AS User
			ON Users.UID=User.UID
			WHERE (NetID='".$_POST["netid"]."' AND NetID<>'') OR (RUID='".mysqli_real_escape_string($mysqli, $_POST["ruid"])."' AND RUID<>'') OR ((FirstName='".mysqli_real_escape_string($mysqli, $_POST["firstname"])."') AND (LastName='".mysqli_real_escape_string($mysqli, $_POST["lastname"])."'));";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." checking for existing user:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error checking if user exists: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Make sure no student(s) were found.
		if (mysqli_num_rows($SQLResult) != 0) {
			$Fields = $SQLResult->fetch_assoc();
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new user. Existing user found with NetID: ".$Fields["NetID"].", RUID: ".$Fields["RUID"].", Name: ".$Fields["FirstName"]." ".$Fields["LastName"]."</DIV>\r\n";
			goto CloseSQL;
		}
	}

	//And derived field values can be calculated now.
	//Dates need to be blank if not filled in. User could have erased a date, which then needs to create a change record with a blank date.
	$GraduationUTC = ConvertDate("departdate");
	$SafetyTrainingUTC = ConvertDate("trainingdate");
	$HandsOnTrainingUTC = ConvertDate("handsondate");


	//These check boxes are annoying. If they are not set, just leave the field blank. In the database, 'T' means it's set, blanks means it's not set.
	$PotentialJudge = "";
	if (isset($_POST["potentialjudge"]))
		if ($_POST["potentialjudge"] == "checked")
			$PotentialJudge = "T";

	$CapstoneAdvisor = "";
	if (isset($_POST["capstoneadvisor"]))
		if ($_POST["capstoneadvisor"] == "checked")
			$CapstoneAdvisor = "T";
	
	$NJResident= "";
	if (isset($_POST["njresident"]))
		if ($_POST["njresident"] == "checked")
			$NJResident = "T";



	//If this is a new user and the minimum required fields are provided, get the next user ID.
	if ($UserBeingUpdatedUID == "") {

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
	}


	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "UserStatus", $_POST["userstatus"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "AccessRole", $_POST["accessrole"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "NetID", $_POST["netid"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "RUID", $_POST["ruid"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Major", $_POST["major"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Prefix", $_POST["prefix"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "FirstName", $_POST["firstname"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "MiddleName", $_POST["middlename"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "LastName", $_POST["lastname"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Suffix", $_POST["suffix"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Gender", $_POST["gender"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "OfficialEmail", $_POST["officialemail"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "PreferredEmail", $_POST["preferredemail"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "AlternateEmail", $_POST["alternateemail"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Phone1", $_POST["phone1"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Phone2", $_POST["phone2"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Street1", $_POST["street1"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Street2", $_POST["street2"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "City", $_POST["city"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "State", $_POST["state"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Zip", $_POST["zip"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Country", $_POST["country"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "StudentType", $_POST["studenttype"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Track", $_POST["track"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "DepartureDateUTC", $GraduationUTC);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "SafetyTrainingUTC", $SafetyTrainingUTC);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "HandsOnTrainingUTC", $HandsOnTrainingUTC);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Advisor1UID", $_POST["advisor1uid"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Advisor2UID", $_POST["advisor2uid"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "NJResident", $NJResident);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Credits", $_POST["credits"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Citizen", $_POST["citizen"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Visa", $_POST["visa"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "EmployeeType", $_POST["employeetype"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Title", $_POST["title"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Office", $_POST["office"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Company",$_POST["company"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "PotentialJudge", $PotentialJudge);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CapstoneAdvisor", $CapstoneAdvisor);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Password1", $_POST["password1"]);
	$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Password2", $_POST["password2"]);
	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Notes", $_POST["newnotes"]);

	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"UsersCreateForm.php?userid=".$UserBeingUpdatedUID."\">User ".$UserBeingUpdatedUID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"UsersCreateForm.php?userid=".$UserBeingUpdatedUID."\">User ".$UserBeingUpdatedUID."</A>.";

    echo " Back to <A HREF=\"UsersCreateList.php?statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&ruidcheck=".$RUIDCheck."&ruidsearch=".urlencode($RUIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&gendercheck=".$GenderCheck."&gendersearch=".urlencode($GenderSearch)."&classcheck=".$ClassCheck."&classsearch=".urlencode($ClassSearch)."&studenttypecheck=".$StudentTypeCheck."&studenttypesearch=".urlencode($StudentTypeSearch)."&employeetypecheck=".$EmployeeTypeCheck."&employeetypesearch=".urlencode($EmployeeTypeSearch)."&majorcheck=".$MajorCheck."&majorsearch=".urlencode($MajorSearch)."&trackcheck=".$TrackCheck."&tracksearch=".urlencode($TrackSearch).
		"&judgecheck=".$JudgeCheck."&advisorcheck=".$AdvisorCheck."&missingcheck=".$MissingCheck."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.</DIV></P>\r\n";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersProcessForm.log"); 
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
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is invalid.</DIV>";
			return TRUE;
		}

		//We still have to check to make sure the year is reasonable.
		if ($Date[2] < 2000) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." year is too far back.</DIV>";
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
