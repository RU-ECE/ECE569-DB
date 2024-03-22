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
    $Title = "Keys";
	$Menu = "Keys";
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
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit key.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to create or edit a key using this page.</DIV>";
		goto CloseSQL;
    }


	//If this is an update to an existing key, the KeyID of that key comes through in a hidden field.
	$KeyID = "";
    if (isset($_POST["keyid"]))
		if (!ValidateField(FALSE, "keyid", "Key ID", "0123456789", 1, 10, FALSE))
		    $KeyID = $_POST["keyid"];


	//Check all the form fields and store the new values.
	$Errors = FALSE;
	$Errors |= ValidateField(FALSE, "status", "Key Status", "LMDRUX", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "roomnumber", "Room Number", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 1, 10, FALSE);
	$Errors |= ValidateField(TRUE, "keycode", "Key Code", "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 0, 10, FALSE);
	$Errors |= ValidateField(FALSE, "keynumber", "Key Number", "0123456789", 1, 3, FALSE);
	$Errors |= ValidateField(FALSE, "netid", "NetID", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 1, 25, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	//Any derived field values can be calculated now.

	//Lookup the UserID based on the NetID of the person that is borrowing they key.
	$UserID = "";
	if ($_POST["netid"] != "") {

		$SQLQuery = "SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$_POST["netid"]."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for netID ".$_POST["netid"]." has failed: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Check if user was found.
		if ($SQLResult->num_rows == 0) {
   			WriteLog("User with NetID ".$_POST["netid"]." not found.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User with NetID ".$_POST["netid"]." not found.</DIV>";
			goto CloseSQL;
		}

		if ($SQLResult->num_rows > 1) {
   			WriteLog($SQLResult->num_rows." users with NetID ".$_POST["netid"]." found.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Multiple users with NetID ".$_POST["netid"]." found.</DIV>";
			goto CloseSQL;
		}

		$Fields = $SQLResult->fetch_row();
		$UserID = $Fields[0];
	}


	//It would be good to check to make sure there isn't already a key code with this key number in the database.
	// This only needs to be done if this is a new key.
	if ($KeyID == "") {

		//I don't understand why this doesn't work. Gives a column not found error..
		//$SQLQuery = "SELECT
		//		KID,
		//		(SELECT Value FROM KeyRecords WHERE Field='KeyCode' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyCode,
		//		(SELECT Value FROM KeyRecords WHERE Field='KeyNumber' AND ID=Keys.KID  ORDER BY CreateTime DESC LIMIT 1) AS KeyNumber
 		//FROM `Keys`
		//	WHERE KeyCode='".$_POST["keycode"]."' AND KeyNumber='".$_POST["keynumber"]."';";

	   $SQLQuery = "SELECT Keys.KID, Key.KeyCode, Key.KeyNumber FROM `Keys`
			LEFT JOIN
				(SELECT KID,
					(SELECT Value FROM KeyRecords WHERE Field='KeyCode' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyCode,
					(SELECT Value FROM KeyRecords WHERE Field='KeyNumber' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyNumber
				FROM `Keys`) AS `Key`
			ON Keys.KID=Key.KID
			WHERE Key.KeyCode='".$_POST["keycode"]."' AND Key.KeyNumber='".$_POST["keynumber"]."';";

		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for key number ".$_POST["keynumber"]." with key code ".$_POST["keycode"]." has failed: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Check if this key already exists.
		if ($SQLResult->num_rows > 0) {
			$Fields = $SQLResult->fetch_row();
   			WriteLog("Key number ".$_POST["keynumber"]." with key code ".$_POST["keycode"]." already exists.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Key number ".$_POST["keynumber"]." with key code ".$_POST["keycode"]." already exists with key ID <A HREF=\"KeysCreateForm.php?keyid=".$Fields[0]."\">".$Fields[0]."</A>.</DIV>";
			goto CloseSQL;
		}
	}


	//Consolidated "Back to previoius search" handling.
    $StatusCheck = "";
    if (isset($_POST["statuscheck"]))
		if (!ValidateField(FALSE, "statuscheck", "Status Check", "checked", 0, 7, FALSE))
			$StatusCheck = $_POST["statuscheck"];

    $StatusSearch = "";
    if (isset($_POST["statussearch"]))
		if (!ValidateField(FALSE, "statussearch", "Status Search", "LMDRUX", 0, 7, FALSE))
			$StatusSearch = $_POST["statussearch"];

    $NetIDCheck = "";
    if (isset($_POST["netidcheck"]))
		if (!ValidateField(FALSE, "netidcheck", "NetID Check", "checked", 0, 7, FALSE))
			$NetIDCheck = $_POST["netidcheck"];

    $NetIDSearch = "";
    if (isset($_POST["netidsearch"]))
		if (!ValidateField(FALSE, "netidsearch", "NetID Search", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 0, 20, FALSE))
		    $NetIDSearch = $_POST["netidsearch"];

    $NameCheck = "";
    if (isset($_POST["namecheck"]))
		if (!ValidateField(FALSE, "namecheck", "Name Check", "checked", 0, 7, FALSE))
	        $NameCheck = $_POST["namecheck"];

    $NameSearch = "";
    if (isset($_POST["namesearch"]))
		if (!ValidateField(FALSE, "namesearch", "Name Search", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-' ", 0, 30, FALSE))
			$NameSearch = $_POST["namesearch"];

    $RoomNumberCheck = "";
    if (isset($_POST["roomnumbercheck"]))
		if (!ValidateField(FALSE, "roomnumbercheck", "Room Number Check", "checked", 0, 7, FALSE))
		    $RoomNumberCheck = $_POST["roomnumbercheck"];

    $RoomNumberSearch = "";
    if (isset($_POST["roomnumbersearch"]))
		if (!ValidateField(FALSE, "roomnumbersearch", "Room Number Search", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 0, 10, FALSE))
	        $RoomNumberSearch = $_POST["roomnumbersearch"];

    $KeyCodeCheck = "";
    if (isset($_POST["keycodecheck"]))
		if (!ValidateField(FALSE, "keycodecheck", "Key Code Check", "checked", 0, 7, FALSE))
			$KeyCodeCheck = $_POST["keycodecheck"];

    $KeyCodeSearch = "";
    if (isset($_POST["keycodesearch"]))
		if (!ValidateField(FALSE, "keycodesearch", "Key Code Search", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 0, 10, FALSE))
		    $KeyCodeSearch = $_POST["keycodesearch"];

    $Sort1 = "";
    if (isset($_POST["sort1"]))
		if (!ValidateField(FALSE, "sort1", "First Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 0, 50, FALSE))
		    $Sort1 = $_POST["sort1"];

    $Sort2 = "";
    if (isset($_POST["sort2"]))
		if (!ValidateField(FALSE, "sort2", "Second Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 0, 50, FALSE))
		    $Sort2 = $_POST["sort2"];


	//If this is a new key create a new key record.
    if ($KeyID == "") {

		$SQLQuery = "INSERT INTO `Keys` (
			CreateTime
		) VALUES (
			'".$CurrentTime."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." creating key:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new key: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Get the ID of this key. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." getting ID of new key:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new key: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$KeyID = $Fields[0];
	}

	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField($KeyID, $UserDoingUpdateUID, $CurrentTime, "Status", $_POST["status"]);
	$ChangeFlag |= UpdateField($KeyID, $UserDoingUpdateUID, $CurrentTime, "RoomNumber", $_POST["roomnumber"]);
	$ChangeFlag |= UpdateField($KeyID, $UserDoingUpdateUID, $CurrentTime, "KeyCode", $_POST["keycode"]);
	$ChangeFlag |= UpdateField($KeyID, $UserDoingUpdateUID, $CurrentTime, "KeyNumber", $_POST["keynumber"]);
	$ChangeFlag |= UpdateField($KeyID, $UserDoingUpdateUID, $CurrentTime, "UserID", $UserID);

	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField($KeyID, $UserDoingUpdateUID, $CurrentTime, "Notes", $_POST["newnotes"]);

	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"KeysCreateForm.php?keyid=".$KeyID."\">Key ".$KeyID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"KeysCreateForm.php?keyid=".$KeyID."\">Key ".$KeyID."</A>.";

    echo " Back to <A HREF=\"KeysCreateList.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&roomnumbercheck=".$RoomNumberCheck."&roomnumbersearch=".urlencode($RoomNumberSearch)."&keycodecheck=".$KeyCodeCheck."&keycodesearch=".urlencode($KeyCodeSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.\r\n";
	// 8/14/2023 Added all the search fileds so previous search will work when duplicating a key.
	echo " Create <A HREF=\"KeysCreateForm.php?keyid=".-$KeyID."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&roomnumbercheck=".$RoomNumberCheck."&roomnumbersearch=".urlencode($RoomNumberSearch)."&keycodecheck=".$KeyCodeCheck."&keycodesearch=".urlencode($KeyCodeSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">duplicate</A>.</DIV>";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Keys/KeysProcessForm.log"); 
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
	function UpdateField($IDBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM KeyRecords WHERE ID=".$IDBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
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
		$SQLQuery = "INSERT INTO KeyRecords (
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
