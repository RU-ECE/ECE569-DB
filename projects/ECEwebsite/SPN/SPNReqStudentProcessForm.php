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
	$Menu = "SPN Requests";
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
	$CurrentYear = date('Y', $CurrentTime);

    //Page content starts here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";


	//Access Control.
    //Student only for this version of the page.
    if ($UserDoingUpdateAccessRole != "D") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a student to create or edit an SPN request using this page.</DIV>";
		goto CloseSQL;
    }

	//If this is an update to an existing SPN request, the SPNID of that request comes through in a hidden field.
	$SPNReqID = "";
    if (isset($_POST["spnid"]))
		if (!ValidateField(FALSE, "spnid", "SPN ID", "0123456789", 1, 10, FALSE))
		    $SPNReqID = $_POST["spnid"];

	$SemesterList = array
      (
	  ""=>"",
      "S"=>"Spring",
	  "M"=>"Summer",
      "F"=>"Fall"
      );


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_POST["statuscheck"]))
		if (!ValidateField(FALSE, "statuscheck", "Status Check", "checked", 0, 7, FALSE))
		    $StatusCheck = $_POST["statuscheck"];

    $StatusSearch = "";
    if (isset($_POST["statussearch"]))
		if (!ValidateField(FALSE, "statussearch", "Status Search", "RPASNVCD", 0, 1, FALSE))
	        $StatusSearch = $_POST["statussearch"];

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

	$Sort1 = "";
    if (isset($_POST["sort1"]))
		if (!ValidateField(FALSE, "sort1", "First Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
		    $Sort1 = $_POST["sort1"];

	$Sort2 = "";
    if (isset($_POST["sort2"]))
		if (!ValidateField(FALSE, "sort2", "Second Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
		    $Sort2 = $_POST["sort2"];


	//Check all the form fields and store the new values.
	$Errors = FALSE;
	$Errors |= ValidateField(FALSE, "spnid", "SPN ID", "0123456789", 1, 10, FALSE);
	$Errors |= ValidateField(TRUE, "status", "Request Status", "RPASNVCD", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "title", "Course Title", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-:& ", 1, 50, FALSE);
	$Errors |= ValidateField(TRUE, "type", "Type", "UGSI", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "course", "Course Number", "0123456789", 3, 3, FALSE);
	$Errors |= ValidateField(TRUE, "section", "Course Section", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 2, 2, FALSE);
	$Errors |= ValidateField(TRUE, "semester", "Semester", "FMS", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "year", "Year", "0123456789", $CurrentYear, $CurrentYear + 1, TRUE);
	$Errors |= ValidateField(FALSE, "newnotes", "New Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 2000, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	//Any derived field values can be calculated now.


	//If this is a new request, check to make sure this isn't a duplicate request.
    if ($SPNReqID == 0) {

		$SQLQuery = "SELECT SPNRequests.SID, SPNRequests.CreateTime, SPNRequest.Status, SPNRequest.Type, SPNRequest.Year, SPNRequest.Semester, SPNRequest.Course, SPNRequest.Section FROM SPNRequests
			LEFT JOIN
				(SELECT SID,
					(SELECT Value FROM SPNRequestRecords WHERE Field='UserID' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS UserID,
					(SELECT Value FROM SPNRequestRecords WHERE Field='Status' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Status,
					(SELECT Value FROM SPNRequestRecords WHERE Field='Type' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Type,
					(SELECT Value FROM SPNRequestRecords WHERE Field='Year' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Year,
					(SELECT Value FROM SPNRequestRecords WHERE Field='Semester' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Semester,
					(SELECT Value FROM SPNRequestRecords WHERE Field='Course' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Course,
					(SELECT Value FROM SPNRequestRecords WHERE Field='Section' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Section
				FROM SPNRequests) AS SPNRequest
			ON SPNRequests.SID=SPNRequest.SID
			WHERE UserID=".$UserDoingUpdateUID." AND Status<>'C' AND Type='".$_POST["type"]."' AND Year='".$_POST["year"]."' AND Semester='".$_POST["semester"]."' AND Course='".$_POST["course"]."' AND Section='".$_POST["section"]."';";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get your active SPN requests: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

	    //If anything was found, there is a duplicate.
	    if ($SQLResult->num_rows > 0) {
			$Fields = $SQLResult->fetch_assoc();
			WriteLog("Duplicate SPN request from ".$UserDoingUpdateNetID." for ".$_POST["semester"]." ".$_POST["year"]." ".$_POST["course"].":".$_POST["section"]);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">An <A HREF=\"SPNReqStudentCreateForm.php?spnid=".$Fields["SID"]."\">SPN request</A> for this course and section was already submitted on ".date("F j, Y, g:i a", $Fields["CreateTime"])."</DIV>";
			goto CloseSQL;
		}		
	}


	//If this is an existing request, check for allowed changes and set defaults.
    if ($SPNReqID != 0) {

		//Retrieve existing status and type, because there are restrictions on what students can change.
		$Status = RetrieveField("SPNRequestRecords", $SPNReqID, "Status"); 

		//The only status change students are allow to make is between "Requested" and "Cancelled"
		if ( ($Status == "C") && isset($_POST["uncancel"]) )
			$Status = "R";
		else if ( ($Status == "R") && isset($_POST["cancel"]) )
			$Status = "C";

		//Retrieve existing UserID, to make sure this is their request.
		$UserID = RetrieveField("SPNRequestRecords", $SPNReqID, "UserID"); 

		if ($UserDoingUpdateUID != $UserID) {
			WriteLog("UserID ".$UserDoingUpdateUID." attempting to edit SPN Request for UserID ".$UserID);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You can only edit your own SPN Requests.</DIV>";
			goto CloseSQL;
		}
	}


	//If this is a new SPN request create a new SPN request record.
    if ($SPNReqID == 0) {

		//DOESN'T THIS NEED TO BE ALL IN A SINGLE TRANSACTION!??

		$SQLQuery = "INSERT INTO `SPNRequests` (
			CreateTime
		) VALUES (
			'".$CurrentTime."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." creating SPN request:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to create new SPN request: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Get the ID of this SPN request. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." getting ID of new SPN request:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get ID of new SPN request: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}
		$Fields = $SQLResult->fetch_row();
		$SPNReqID = $Fields[0];

		//Status needs to be defaulted to "Requested" for new requests
		$Status = "R";
	}


	//Finally we can update the rest of the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag = $StatusChangeFlag = UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Status", $Status);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Type", $_POST["type"]);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "UserID", $UserDoingUpdateUID);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Title", $_POST["title"]);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Course", $_POST["course"]);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Section", $_POST["section"]);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Year", $_POST["year"]);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Semester", $_POST["semester"]);

	//I had to do this because a blank notes field will cause a new blank note to be posted by the logic in UpdateField().
	if ($_POST["newnotes"] != "")
		$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Notes", mysqli_real_escape_string($mysqli, $_POST["newnotes"]));


	//SEND THE VARIOUS EMAILS
	//If the status was changed to "Requested", we have to send an email to the approver.
	if ( ($Status == "R") && $StatusChangeFlag) {

		$RequestEmailUID = RetrieveField("SPNConfig", 0, "RequestEmailUID");
		$RequestEmail = RetrieveField("UserRecords", $RequestEmailUID, "PreferredEmail");

		//If no email address was found, then do nothing.
		if ($RequestEmail != "") {

			//Setup the email message.
			$EmailMessage = "Hello,\r\n\r\nAn SPN request was just submitted by ".$UserDoingUpdateNetID." for\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\n\r\nPlease login to ECEAPPS to review the request: https://apps.ece.rutgers.edu/SPN/SPNReqStaffCreateForm.php?spnid=".$SPNReqID."\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequestEmail, "SPN Request", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\n");

			WriteLog("Sent SPN email alert to ".$RequestEmail);
		}
	}


	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"SPNReqStudentCreateForm.php?spnid=".$SPNReqID."\">SPN Request ".$SPNReqID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"SPNReqStudentCreateForm.php?spnid=".$SPNReqID."\">SPN Request ".$SPNReqID."</A>.";

    echo " Back to <A HREF=\"SPNReqStudentCreateList.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionearch=".urlencode($SectionSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.</DIV>\r\n";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNReqStudentProcessForm.log"); 
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

	//Function to retrieve a specified field for a specified user.
	// This is here because there are some field checks that require the prior value of the field.
    function RetrieveField($Table, $UID, $Field) {

        global $mysqli;


		//This has to be done this way, otherwise if UID is zero, empty string will be returned.
		if (strlen($UID) == 0)
			return "";

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
	        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve field from database: ".$mysqli->error."</DIV>";
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
