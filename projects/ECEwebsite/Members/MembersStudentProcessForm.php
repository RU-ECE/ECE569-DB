<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

    //Set for the title of the page.
    $title = "ECE Apps - Capstone";
	$Menu = "Capstone";
    $UserDoingUpdateAccessRole = "";

    //If the user is already logged in, their ID will have been saved in the current session.  
	if (empty($_SESSION['netid'])) {
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You have to login first in order to access this page.</DIV>";
		goto SendTailer;
	}
	
    //Moved here to after the login check to avoid error messages printing by PHP.
    $UserDoingUpdateNetID = $_SESSION['netid'];

//TEMP TESTING
//$UserDoingUpdateNetID = "kde32";
//$UserDoingUpdateNetID = "rtm121";

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
    //Students only for this version of the page.
    if ($UserDoingUpdateAccessRole != "D") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit a Member.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a student to create or edit a Member using this page.</DIV>";
		goto CloseSQL;
    }


	//If this is an update to an existing Member, the MemberID comes through in a hidden field.
	$MemberID = "";
    if (isset($_POST["mid"]))
		if (!ValidateField(FALSE, "mid", "Member ID", "0123456789", 1, 10, FALSE))
		    $MemberID = $_POST["mid"];


	//Check all the form fields.
	$Errors = FALSE;
	$Errors |= ValidateField(TRUE, "status", "Status", "AD", 1, 1, FALSE);
//	$Errors |= ValidateField(TRUE, "type", "Type", "SAJ", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "semester", "Semester", "SMF", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "year", "Year", "0123456789", 1, 4, FALSE);
	$Errors |= ValidateField(TRUE, "team", "Team Number", "0123456789", 1, 3, FALSE);
//	$Errors |= ValidateField(FALSE, "netid", "NetID", "abcdefghijklmnopqrstuvwxyz0123456789", 0, 25, FALSE);
//	$Errors |= ValidateField(FALSE, "advidorid", "AdvisorID", "0123456789", 1, 10, FALSE);
	$Errors |= ValidateField(FALSE, "newnotes", "New Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 2000, FALSE);


	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form.</DIV>";
		goto CloseSQL;
	}


	//Students are only allowed to change teams until just after the Round 1 Presentations.
	$SpringFormedDateUTC = RetrieveField("CapstoneConfig", "0", "SpringFormedDateUTC");
	if ($CurrentTime > $SpringFormedDateUTC) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">The cutoff date has passed for changing team membership. Please contact your capstone advisor for changes.</DIV>";
		goto CloseSQL;
	}

/*
	//These have to be saved as variables to avoid undefined errors.
	$NetID = "";
	if (isset($_POST["netid"]))
		$NetID = $_POST["netid"];

	$AdvisorID = "";
	if (isset($_POST["advisorid"]))
		$AdvisorID = $_POST["advisorid"];
*/

/*
	//If a NetID was provided, then lookup the new member based on NetID
	$UserID = "";
	if ($NetID != "") {

		$SQLQuery = "SELECT Users.UID, User.NetID, User.UserStatus FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
					(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus
				FROM Users) AS User
			ON Users.UID=User.UID
			WHERE NetID='".$_POST["netid"]."' AND UserStatus='A';";
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
			$Errors = TRUE;
		}
		else {
			$Fields = $SQLResult->fetch_row();
			$UserID = $Fields[0];
		}
*/

/*
		//Students can only edit their own member records.
		if ($MemberID != 0) {
			WriteLog("Student ID ".$UserDoingUpdateUID." trying to edit member ID ".$Fields["MID"]." for Student ID ".$Fields["UserID"]);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You can only edit your own team membership.</DIV>";
			goto CloseSQL;
		}
*/
//	}

/*
	//If an AdvisorID was provided, then use that as the new user ID.
	if ($AdvisorID != "") {
		$UserID = $AdvisorID;
	}


	//Either user or advisor must be provided, but not neither and not both.
	if ( !(($NetID == "") xor ($AdvisorID == "")) ) {
   		WriteLog("UserID and AdvisorID both blank or both provided:".$NetID." ".$AdvisorID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must provide either a NetID of the new team member or select an advisor, but not both.</DIV>";
		$Errors = TRUE;
	}
*/



	//We need the UserID connected to the MemberID, to check to make sure the user isn't trying to edit another user's membership.
	if ($MemberID != 0) {

	    $SQLQuery = "SELECT ID, Value FROM MemberRecords WHERE Field='UserID' AND ID=".$MemberID." ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for UserID ".$UserDoingUpdateUID." has failed: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Check if user was found.
		if ($SQLResult->num_rows != 1) {
   			WriteLog("UserID ".$UserDoingUpdateUID." not found in Member record ".$MemberID." rows=".$SQLResult->num_rows);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User ID not found in Member record.</DIV>";
			goto CloseSQL;
		}

		$Fields = $SQLResult->fetch_row();
		$UserID = $Fields[1];

		if ($UserDoingUpdateUID != $UserID) {
  			WriteLog("UserID ".$UserDoingUpdateUID." trying to edit MemberID ".$MemberID." with UserID ".$UserID);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You cannot edit the team membership of another user.</DIV>";
			goto CloseSQL;
		}
	}



	//Check if this is a new student team member and if so, make sure they are not already on this or another team.
	if ($MemberID == 0) {

		$SQLQuery = "SELECT Member.MID, Member.Status, Member.Type, Member.TeamID, Member.UserID, Team.Semester, Team.Year, Team.Number, Team.Title FROM Members
			LEFT JOIN
				(SELECT MID,
					(SELECT Value FROM MemberRecords WHERE Field='Status' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Status,
					(SELECT Value FROM MemberRecords WHERE Field='Type' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Type,
					(SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS TeamID,
					(SELECT Value FROM MemberRecords WHERE Field='UserID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS UserID
				FROM Members) AS Member
			ON Member.MID=Members.MID
			LEFT JOIN
				(SELECT TID, Semester, Year, Number,
					(SELECT Value FROM TeamRecords WHERE Field='Title' AND ID=Teams.TID ORDER BY CreateTime DESC LIMIT 1) AS Title
				FROM Teams) AS Team
			ON Team.TID=Member.TeamID
			WHERE Status='A' AND Type='S' AND UserID='".$UserDoingUpdateUID."';";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to check existing team membership:".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//If student is active and already on this or another team it's an error and no changes will be made until fixed.
		if ($SQLResult->num_rows != 0) {
			$Fields = $SQLResult->fetch_assoc();
			WriteLog("StudentID ".$UserDoingUpdateUID." already on team ".$Fields["Semester"].$Fields["Year"]."-".$Fields["Number"]." rows=".$SQLResult->num_rows);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You are already on team ".$Fields["Semester"].substr($Fields["Year"], -2)."-".$Fields["Number"].", ".$Fields["Title"].". If you are trying to move to a different team, edit your existing team membership by clicking on your name in the team members list.</DIV>";
			goto CloseSQL;
		}
	}


	//We also have to get the TeamID based on the semester, year, and team number, because the team membership is stored as a TeamID.
	// I guess this could have been passed in a hidden field but looking it up is safer.
	$SQLQuery = "SELECT Teams.TID FROM Teams WHERE Semester='".$_POST["semester"]."' AND Year=".$_POST["year"]." AND Number=".$_POST["team"].";";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for netID ".$_POST["netid"]." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if team was found.
	if ($SQLResult->num_rows != 1) {
   		WriteLog("Team ".$_POST["semester"].$_POST["year"]."-".$_POST["team"]." not found ".$SQLResult->num_rows);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Team ".$_POST["semester"].$_POST["year"]."-".$_POST["team"]." not found.</DIV>";
		goto CloseSQL;
	}

	$Fields = $SQLResult->fetch_row();
	$TeamID = $Fields[0];
	//There is no need to check the team number against the member team number, since the student is allowed to change it.


	//Teams are only allowed to have 5 members..
	$SQLQuery = "SELECT Member.MID, Member.Status, Member.Type, Member.TeamID FROM Members
		LEFT JOIN
			(SELECT MID,
				(SELECT Value FROM MemberRecords WHERE Field='Status' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				(SELECT Value FROM MemberRecords WHERE Field='Type' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Type,
				(SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS TeamID
			FROM Members) AS Member
		ON Member.MID=Members.MID
		WHERE Status='A' AND Type='S' AND TeamID='".$TeamID."';";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to check existing team membership:".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	if ($SQLResult->num_rows >= 5) {
  		WriteLog("Team full. rows=".$SQLResult->num_rows);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">This teams already has the maximum number of members.</DIV>";
		goto CloseSQL;
	}


	//If this is a new Member create a new record.
    if ($MemberID == 0) {

		$SQLQuery = "INSERT INTO `Members` (
			CreateTime
		) VALUES (
			'".$CurrentTime."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." creating Member:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new Member: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Get the ID of this Member. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." getting ID of new Member:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new Member: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$MemberID = $Fields[0];
	}

	//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = FALSE;
	$ChangeFlag |= UpdateField("MemberRecords", $MemberID, $UserDoingUpdateUID, $CurrentTime, "Status", $_POST["status"]);
	$ChangeFlag |= UpdateField("MemberRecords", $MemberID, $UserDoingUpdateUID, $CurrentTime, "Type", "S");
	$ChangeFlag |= UpdateField("MemberRecords", $MemberID, $UserDoingUpdateUID, $CurrentTime, "UserID", $UserDoingUpdateUID);
	$ChangeFlag |= UpdateField("MemberRecords", $MemberID, $UserDoingUpdateUID, $CurrentTime, "TeamID", $TeamID);

	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField("MemberRecords", $MemberID, $UserDoingUpdateUID, $CurrentTime, "Notes", $_POST["newnotes"]);


	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"MembersStudentCreateForm.php?mid=".$MemberID."\">Member ".$MemberID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"MembersStudentCreateForm.php?mid=".$MemberID."\">Member ".$MemberID."</A>.";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Members/MembersStudentProcessForm.log"); 
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
			echo "<P>Failed to add change record: ".$mysqli->error."</P>";
			return FALSE;
		}

		return TRUE;
    }


	//Function to retrieve a specified field for a specified user.
	function RetrieveField($Table, $ID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$ID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve user field ".$Field." with ID ".$ID." from ".$Table.": ".$mysqli->error."</DIV>";
		    return "";
	    }

        //Not all fields are defined for every user, so it is not an error for the field to not be found.
	    if ($SQLResult->num_rows == 1) {
        	$Fields = $SQLResult->fetch_row();
            return $Fields[0];
        }
        else {
            return "";
        }
    }
?>
