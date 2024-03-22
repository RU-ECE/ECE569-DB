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
    $Title = "Equipment";
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
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit Equipment.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

	//If this is an update to existing equipment, the ID of that user comes through in a hidden field.
	$EquipmentID = 0;
    if (isset($_POST["equipid"]))
		if (!ValidateField(TRUE, "equipid", "Equipment ID", "0123456789", 1, 10, FALSE))
		    $EquipmentID = $_POST["equipid"];


	//Check all the form fields and store the new value.
	$Errors = FALSE;
	$Errors |= ValidateField(TRUE, "status", "Equipment Status", "ALSBMRHPDTU", 1, 1, FALSE);
	$Errors |= ValidateField(FALSE, "ecetag", "ECE Tag Number", "0123456789/-. ", 1, 20, FALSE);
	$Errors |= ValidateField(FALSE, "rutag", "RU Tag Number", "0123456789/-. ", 0, 20, FALSE);
	$Errors |= ValidateField(FALSE, "make", "Manufacturer", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:',<.>/? ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "model", "Model", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:',<.>/? ", 0, 50, FALSE);
	$Errors |= ValidateField(FALSE, "serial", "Serial Number", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:',<.>/? ", 0, 30, FALSE);
	$Errors |= ValidateField(FALSE, "type", "Equipment Type", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:',<.>/? ", 0, 40, FALSE);
	$Errors |= ValidateField(FALSE, "description", "Description", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:',<.>/? ", 0, 50, FALSE);
	$Errors |= ValidateDate("acquiredate", "Acquire Date");
	$Errors |= ValidateField(FALSE, "cost", "Cost", "0123456789.-", 0, 10, FALSE);
	$Errors |= ValidateField(FALSE, "ponumber", "PO Number", "0123456789", 0, 10, FALSE);
	$Errors |= ValidateField(FALSE, "project", "Project/GL String", "0123456789/", 0, 10, FALSE);
	$Errors |= ValidateField(FALSE, "location", "Location", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:',<.>/? ", 1, 10, FALSE);
	$Errors |= ValidateField(FALSE, "ownernetid", "Owner NetID", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.-", 0, 20, FALSE);

	//Additional field validation goes here..

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	//Any derived field values can be calculated now.
	//Dates need to be blank if not filled in. User could have erased a date, which then needs to create a change record with a blank date.
	$AcquireDateUTC = ConvertDate("acquiredate");


	//If an owner NetID was provided, then we have to lookup the ID associated with that NetID.
	$OwnerID = "";
	if ($_POST["ownernetid"] != "") {

		$SQLQuery = "SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$_POST["ownernetid"]."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for user with NetID ".$_POST["ownernetid"]." has failed: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Check if user was found.
		if ($SQLResult->num_rows == 0) {
   			WriteLog("User with NetID ".$_POST["ownernetid"]." not found.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User with NetID ".$_POST["ownernetid"]." not found.</DIV>";
	    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
			goto CloseSQL;
		}

		if ($SQLResult->num_rows > 1) {
   			WriteLog($SQLResult->num_rows." users with NetID ".$_POST["ownernetid"]." found.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Multiple users with NetID ".$_POST["ownernetid"]." found.</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$OwnerID = $Fields[0];
	}


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_POST["statuscheck"]))
		if (!ValidateField(FALSE, "statuscheck", "Status Check", "checked", 0, 7, FALSE))
	        $StatusCheck = $_POST["statuscheck"];

    $StatusSearch = "";
    if (isset($_POST["statussearch"]))
		if (!ValidateField(FALSE, "statussearch", "Status Search", "ALSBMRHPDTU", 0, 3, FALSE))
			$StatusSearch = $_POST["statussearch"];

    $TagCheck = "";
    if (isset($_POST["tagcheck"]))
		if (!ValidateField(FALSE, "tagcheck", "Tag Check", "checked", 0, 7, FALSE))
			$TagCheck = $_POST["tagcheck"];

    $ECETagSearch = "";
    if (isset($_POST["ecetagsearch"]))
		if (!ValidateField(FALSE, "ecetagsearch", "ECE Tag", "0123456789", 0, 3, FALSE))
			$ECETagSearch = $_POST["ecetagsearch"];

    $RUTagSearch = "";
    if (isset($_POST["rutagsearch"]))
		if (!ValidateField(FALSE, "rutagsearch", "RU Tag", "0123456789/", 0, 1, FALSE))
			$RUTagSearch = $_POST["rutagsearch"];

	$NetIDCheck = "";
    if (isset($_POST["netidcheck"]))
		if (!ValidateField(FALSE, "netidcheck", "NetID Check", "checked", 0, 7, FALSE))
			$NetIDCheck = $_POST["netidcheck"];

	$NetIDSearch = "";
    if (isset($_POST["netidsearch"]))
		if (!ValidateField(FALSE, "netidsearch", "NetID", "abcdefghijklmnopqrstuvwxyz0123456789", 0, 25, FALSE))
			$NetIDSearch = $_POST["netidsearch"];

    $TypeCheck = "";
    if (isset($_POST["typecheck"]))
		if (!ValidateField(FALSE, "typecheck", "Type Check", "checked", 0, 7, FALSE))
			$TypeCheck = $_POST["typecheck"];

    $TypeSearch = "";
    if (isset($_POST["typesearch"]))
		if (!ValidateField(FALSE, "typesearch", "Equipment Type", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789- ", 0, 30, FALSE))
			$TypeSearch = $_POST["typesearch"];

    $MakeCheck = "";
    if (isset($_POST["makecheck"]))
		if (!ValidateField(FALSE, "makecheck", "Manufacturer Check", "checked", 0, 7, FALSE))
			$MakeCheck = $_POST["makecheck"];

    $MakeSearch = "";
    if (isset($_POST["makesearch"]))
		if (!ValidateField(FALSE, "makesearch", "Manufacturer Search", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789- ", 0, 30, FALSE))
			$MakeSearch = $_POST["makesearch"];

    $LocationCheck = "";
    if (isset($_POST["locationcheck"]))
		if (!ValidateField(FALSE, "locationcheck", "Location Check", "checked", 0, 7, FALSE))
			$LocationCheck = $_POST["locationcheck"];

    $LocationSearch = "";
    if (isset($_POST["locationsearch"]))
		if (!ValidateField(FALSE, "locationsearch", "Location Search", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789- ", 0, 10, FALSE))
			$LocationSearch = $_POST["locationsearch"];

    $Sort1 = "";
    if (isset($_POST["sort1"]))
		if (!ValidateField(FALSE, "sort1", "First Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
			$Sort1 = $_POST["sort1"];

    $Sort2 = "";
    if (isset($_POST["sort2"]))
		if (!ValidateField(FALSE, "sort2", "Second Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
		    $Sort2 = $_POST["sort2"];


	//If this is new equipment and the minimum required fields are provided, get the next equipment ID.
	if ($EquipmentID == 0) {
		$SQLQuery = "INSERT INTO Equip (
			CreateTime
		) VALUES (
			'".$CurrentTime."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." adding equipment:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new equipment: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Get the ID of this equipment. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." getting ID of new equipment:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new equipment: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$EquipmentID = $Fields[0];
	}

	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Status", $_POST["status"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "ECETag", $_POST["ecetag"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "RUTag", $_POST["rutag"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Make", $_POST["make"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Model", $_POST["model"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Serial", $_POST["serial"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Type", $_POST["type"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Description", $_POST["description"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "AcquireDateUTC", $AcquireDateUTC);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Cost", strval($_POST["cost"] * 100));
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "PONumber", $_POST["ponumber"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Project", $_POST["project"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Location", $_POST["location"]);
	$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "OwnerID", $OwnerID);

	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField($EquipmentID, $UserDoingUpdateUID, $CurrentTime, "Notes", $_POST["newnotes"]);

	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"EquipmentCreateForm.php?equipid=".$EquipmentID."\">Equipment ".$EquipmentID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"EquipmentCreateForm.php?equipid=".$EquipmentID."\">Equipment ID".$EquipmentID."</A>.";

    echo " Back to <A HREF=\"EquipmentCreateList.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&tagcheck=".$TagCheck."&ecetagsearch=".urlencode($ECETagSearch)."&rutagsearch=".urlencode($RUTagSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&typecheck=".$TypeCheck."&typesearch=".urlencode($TypeSearch)."&makecheck=".$MakeCheck."&makesearch=".urlencode($MakeSearch)."&locationcheck=".$LocationCheck."&locationsearch=".urlencode($LocationSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.\r\n";
	echo " Create <A HREF=\"EquipmentCreateForm.php?equipid=0\">new equipment</A>.";
	echo " Create <A HREF=\"EquipmentCreateForm.php?equipid=".-$EquipmentID."\">duplicate</A>.</DIV>";

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
    //Function to write a string to the log. I've never had this many problems writing a log file. Make sure directory permissions are 775! Must use absolute path!
    // Let apache create the file. Owner will be apache:ece
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Equipment/EquipmentProcessForm.log"); 
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
		//5/16/2023 - very strange - getting random illegal character errors. Maybe strspn() is failing??
		if ( ($CharPosition = strspn($FieldValue, $ValidChars)) < $FieldLength) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Illegal character in ".$DisplayName." field \"".$FieldValue."\" at position ".$CharPosition.", character '".$FieldValue[$CharPosition]."'</DIV>";
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
	function UpdateField($EquipmentID, $UserID, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM EquipRecords WHERE ID=".$EquipmentID." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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














































