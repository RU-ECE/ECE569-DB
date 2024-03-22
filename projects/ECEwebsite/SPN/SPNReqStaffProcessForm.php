<!--7/24/2023 Modified to just send email to SOE approver expecting an email response, rather than emailing them a link.-->
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

    //Page content starts here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";


	//Access Control.
    //Staff only for this version of the page.
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member of faculty to create or edit an SPN request using this page.</DIV>";
		goto CloseSQL;
    }


	$SemesterList = array
      (
	  ""=>"",
      "S"=>"Spring",
	  "M"=>"Summer",
      "F"=>"Fall"
      );

    //Array of names for type dropdown list box
    $TypeList = array
      (
      "U"=>"Ugrad",
	  "G"=>"Grad",
	  "S"=>"Special Problems",
	  "I"=>"Internship"
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

	$TypeCheck = "";
    if (isset($_POST["typecheck"]))
		if (!ValidateField(FALSE, "typecheck", "Type Check", "checked", 0, 7, FALSE))
		    $TypeCheck = $_POST["typecheck"];

    $TypeSearch = "";
    if (isset($_POST["typesearch"]))
		if (!ValidateField(FALSE, "typesearch", "Type Search", "UG", 0, 1, FALSE))
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

    $NetIDCheck = "";
    if (isset($_POST["netidcheck"]))
		if (!ValidateField(FALSE, "netidcheck", "NetID Check", "checked", 0, 7, FALSE))
	        $NetIDCheck = $_POST["netidcheck"];

    $NetIDSearch = "";
    if (isset($_POST["netidsearch"]))
		if (!ValidateField(FALSE, "netidsearch", "NetID Search", "0123456789abcdefghijklmnopqrstuvwxyz", 0, 25, FALSE))
		    $NetIDSearch = $_POST["netidsearch"];

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

    $Sort3 = "";
    if (isset($_POST["sort3"]))
		if (!ValidateField(FALSE, "sort3", "Third Sort", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 0, 50, FALSE))
			$Sort3 = $_POST["sort3"];


	//If this is an update to an existing SPN request, the SPNID of that request comes through in a hidden field.
	$SPNReqID = "";
    if (isset($_POST["spnid"]))
		if (!ValidateField(FALSE, "spnid", "SPN ID", "0123456789", 1, 10, FALSE))
		    $SPNReqID = $_POST["spnid"];


	//Check all the form fields and store the new values.
	$Errors = FALSE;
	$Errors |= ValidateField(FALSE, "spnid", "SPN ID", "0123456789", 1, 10, FALSE);
	$Errors |= ValidateField(TRUE, "status", "Request Status", "RPASNVCD", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "netid", "NetID", "abcdefghijklmnopqrstuvwxyz0123456789", 0, 25, FALSE);
	$Errors |= ValidateField(TRUE, "title", "Course Title", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-:& ", 1, 50, FALSE);
	$Errors |= ValidateField(TRUE, "type", "Type", "UGSI", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "course", "Course Number", "0123456789", 3, 3, FALSE);
	$Errors |= ValidateField(TRUE, "section", "Course Section", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 2, 2, FALSE);
	$Errors |= ValidateField(TRUE, "semester", "Semester", "FS", 1, 1, FALSE);
	$Errors |= ValidateField(TRUE, "year", "Year", "0123456789", 4, 4, FALSE);
	$Errors |= ValidateField(FALSE, "newnotes", "New Notes", "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ", 0, 2000, FALSE);
/*
	//Validate the uploaded file if there is one.
	if (isset($_FILES["projectdesc"])) {

		if ($_FILES["projectdesc"]["error"] != 0) {
			WriteLog("Error ".$_FILES["projectdesc"]["error"]." uploading file ".$_FILES["projectdesc"]["name"]);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error ".$_FILES["projectdesc"]["error"]." uploading file ".$_FILES["projectdesc"]["name"]."</DIV>";
			$Errors = TRUE;
		}

		$FileExtension = pathinfo($_FILES["projectdesc"]["name"], PATHINFO_EXTENSION);
		if ($FileExtension != ".pdf") {
			WriteLog("File type ".$FileExtension." not allowed.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">File type ".$FileExtension." not allowed. File type must be .pdf.</DIV>";
			$Errors = TRUE;
		}

		//Check the max file size - currently allowing up to 2MB
		if ($_FILES["projectdesc"]["size"] > 2097152) {
			WriteLog("File is too big, ".$_FILES["projectdesc"]["size"]." bytes.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Maximum file size is 2MB. Your file is ".$_FILES["projectdesc"]["size"]." bytes</DIV>";
			$Errors = TRUE;
		}

		if ($_FILES["projectdesc"]["size"] <= 0) {
			WriteLog("File ".$_FILES["projectdesc"]["name"]." is empty.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Your file is empty!</DIV>";
			$Errors = TRUE;
		}

		//Verify the MIME type of the file.
		if ($_FILES["projectdesc"]["type"] != ".pdf") {
 			WriteLog("MIME type ".$_FILES["projectdesc"]["type"]." is not allowed.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Incorrect file type of ".$_FILES["projectdesc"]["type"]." File must be .pdf.</DIV>";
			$Errors = TRUE;
		}
	}
*/

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	//Any derived field values can be calculated now.


	//We need to convert the NetID into a UserID. At the same time, we can get the requesters name and email address which may be needed later.
	$UserID = "";
	$FirstName = "";
	$LastName = "";
	$RUID = "";
	$Major = "";
	$RequesterEmail = "";
	if ($_POST["netid"] != "") {

		//Look for all active users with the NetID.
		$SQLQuery = "SELECT Users.UID, User.UserStatus, User.RUID, User.Major, User.FirstName, User.LastName, User.Email FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
					(SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
					(SELECT Value FROM UserRecords WHERE Field='Major' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Major,
					(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
					(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
					(SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Email
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
		$RUID = $Fields[2];
		$Major = $Fields[3];
		$FirstName = $Fields[4];
		$LastName = $Fields[5];
		$RequesterEmail = $Fields[6];
	}



	//If this is a new request, check to make sure this isn't a duplicate request.
    if ($SPNReqID == 0) {

		$SQLQuery = "SELECT SPNRequests.SID, SPNRequests.CreateTime, SPNRequest.UserID, SPNRequest.Status, SPNRequest.Type, SPNRequest.Year, SPNRequest.Semester, SPNRequest.Course, SPNRequest.Section FROM SPNRequests
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
			WHERE UserID=".$UserID." AND Status<>'C' AND Type='".$_POST["type"]."' AND Year='".$_POST["year"]."' AND Semester='".$_POST["semester"]."' AND Course='".$_POST["course"]."' AND Section='".$_POST["section"]."';";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get your active SPN requests: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

	    //If anything was found, there is a duplicate.
	    if ($SQLResult->num_rows > 0) {
			$Fields = $SQLResult->fetch_assoc();
			WriteLog("Duplicate SPN request.");
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">An <A HREF=\"SPNReqStaffCreateForm.php?spnid=".$Fields["SID"]."\">SPN request</A> for this course and section was already submitted on ".date("F j, Y, g:i a", $Fields["CreateTime"])."</DIV>";
			goto CloseSQL;
		}		
	}

/*
	//If the status just changed to "SOE Approved", make sure this user is an SOE approver.
	if ( ($NewStatus == "W") && ($OldStatus != "W") ) {

		//Get the SOE approver UserID.
		$SOEApproverUID = RetrieveField("SPNConfig", 0, "SOEApproverUID");

		if ($SOEApproverUID != $UserDoingUpdateUID) {
			WriteLog("UserID ".$UserDoingUpdateUID." acting as SOE approver ".$SOEApproverUID);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be an SOE Approver to approve an SPN request from a non-ECE student.</DIV>";
			goto CloseSQL;
		}
	}
*/

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
	}


	//All this has to be done in a transaction block.
	$SQLQuery = "START TRANSACTION;";
	$SQLResult = $mysqli->query($SQLQuery);

	//If the status is "Approved" we need to check if it has just changed to approved, and if so, get the next SPN for this course and mark it used.
	$SPN = "";
	$SPNID = "";
	$RemainingSPNs = 10;
	$NewStatus = $_POST["status"];
	$OldStatus = RetrieveField("SPNRequestRecords", $SPNReqID, "Status");
	if ( ($NewStatus == "A") && ($OldStatus != "A") ) {

		//Get the next SPN for this particular course and section. Return all the available SPNs so we know how many are left so we can warn the user
		//if we are running out of SPNs.
		$SQLQuery = "SELECT SPNs.SID, SPNs.Type, SPNs.Semester, SPNs.Year, SPNs.Course, SPNs.Section, SPNs.SPN, SPN.Status FROM SPNs
			LEFT JOIN
				(SELECT SID,
					(SELECT Value FROM SPNRecords WHERE Field='Status' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS Status
				FROM SPNs) AS SPN
			ON SPNs.SID=SPN.SID
 			WHERE Type='".$_POST["type"]."' AND Year='".$_POST["year"]."' AND Semester='".$_POST["semester"]."' AND Course='".$_POST["course"]."' AND Section='".$_POST["section"]."' AND Status='A' ORDER BY SID;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get SPN: ".$mysqli->error."</DIV>";
		}

		//Check if an SPN was available.
		if ($SQLResult->num_rows < 1) {
   			WriteLog("No ".$TypeList[$_POST["type"]]." SPNs available for ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]." ".$_POST["course"].":".$_POST["section"]);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">There are no more ".$TypeList[$_POST["type"]]." SPNs available for ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]." ".$_POST["course"].":".$_POST["section"]."</DIV>";
			$NewStatus = $OldStatus;
		}
		else {
			//Extract the SPN.
			$Fields = $SQLResult->fetch_row();
			$SPNID = $Fields[0];
			$SPN = $Fields[6];

			//Update the SPN status since it is now used.
			UpdateField("SPNRecords", $SPNID, $UserDoingUpdateUID, $CurrentTime, "Status", "U");
			UpdateField("SPNRecords", $SPNID, $UserDoingUpdateUID, $CurrentTime, "UserID", $UserID);

			//Remember the number of available SPNs remaining.
			$RemainingSPNs = $SQLResult->num_rows - 1;
		}
	}

	//Update the SPN request status.
	$ChangeFlag = UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Status", $NewStatus);

	$SQLQuery = "COMMIT;";
	$SQLResult = $mysqli->query($SQLQuery);


	//Finally we can update the rest of the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Type", $_POST["type"]);
	$ChangeFlag |= UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "UserID", $UserID);
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
	if ( ($NewStatus == "R") && ($OldStatus != "R")) {

		$RequestEmailUID = RetrieveField("SPNConfig", 0, "RequestEmailUID");
		$RequestEmail = RetrieveField("UserRecords", $RequestEmailUID, "PreferredEmail");

		//If no email address was found, then do nothing.
		if ($RequestEmail != "") {

			//Setup the email message.
			$EmailMessage = "Hello,\r\n\r\nAn SPN request was just submitted by ".$_POST["netid"]." for\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\n\r\nPlease login to ECEAPPS to review the <A HREF=\"https://apps.ece.rutgers.edu/SPN/SPNReqStaffCreateForm.php?spnid=".$SPNReqID."\">request</A>.\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequestEmail, "SPN Request", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\n");
		}
	}


	//If the status has changed to "Approved", we have to send an email to requeter.
	if ( ($NewStatus == "A") && ($OldStatus != "A") ) {

		$ReplyToEmailUID = RetrieveField("SPNConfig", 0, "ReplyToEmailUID");
		$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");
		$Email = RetrieveField("SPNConfig", 0, "Email1");

		//If no email address was found, then do nothing.
		if ($RequesterEmail != "") {

			//Setup the email message.
			$EmailMessage = "Dear ".$FirstName.",\r\n\r\n".$Email."\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\nSPN: ".$SPN."\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequesterEmail, "SPN Request Approved", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\nReply-To: ".$ReplyToEmail);

			//Write a change record to note the fact that an email was sent.
			UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Notes", "SPN approved email sent to ".$RequesterEmail.".");
		}
	}


	//If the status was changed to "Pending", we have to send an email to the requester.
	if ( ($NewStatus == "P") && ($OldStatus != "P")) {

		$ReplyToEmailUID = RetrieveField("SPNConfig", 0, "ReplyToEmailUID");
		$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");
		$Email = RetrieveField("SPNConfig", 0, "Email2");

		if ($RequesterEmail != "") {

			//Setup the email message.
			$EmailMessage = "Dear ".$FirstName.",\r\n\r\n".$Email."\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequesterEmail, "SPN Request Pending", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\nReply-To: ".$ReplyToEmail);

			//Write a change record to note the fact that an email was sent.
			UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Notes", "SPN pending email sent to ".$RequesterEmail.".");
		}
	}


	//If the status was changed to "Denied", we have to send an email to the requester.
	if ( ($NewStatus == "D") && ($OldStatus != "D") ) {

		$ReplyToEmailUID = RetrieveField("SPNConfig", 0, "ReplyToEmailUID");
		$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");
		$Email = RetrieveField("SPNConfig", 0, "Email3");

		if ($RequesterEmail != "") {

			//Setup the email message.
			$EmailMessage = "Dear ".$FirstName.",\r\n\r\n".$Email."\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequesterEmail, "SPN Request Denied", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\nReply-To: ".$ReplyToEmail);

			//Write a change record to note the fact that an email was sent.
			UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Notes", "SPN denied email sent to ".$RequesterEmail.".");
		}
	}


	//If the status was changed to "Not ECE", we have to send an email to the requester.
	if ( ($NewStatus == "N") && ($OldStatus != "N") ) {

		$ReplyToEmailUID = RetrieveField("SPNConfig", 0, "ReplyToEmailUID");
		$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");
		$Email = "Your SPN request for the course shown below is not offered by the ECE department. Please contact the department offering the course for details on how to submit an SPN request to them. If you have any further questions, please reply to this email.";

		if ($RequesterEmail != "") {

			//Setup the email message.
			$EmailMessage = "Dear ".$FirstName.",\r\n\r\n".$Email."\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequesterEmail, "SPN Request", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\nReply-To: ".$ReplyToEmail);

			//Write a change record to note the fact that an email was sent.
			UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Notes", "SPN not for ECE course email sent to ".$RequesterEmail.".");
		}
	}


	//If the status was changed to "SOE Review", we have to send an email to the SOE Approver.
	if ( ($NewStatus == "V") && ($OldStatus != "V")) {

		$ReplyToEmailUID = RetrieveField("SPNConfig", 0, "ReplyToEmailUID");
		$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");
		$SOEApproverUID = RetrieveField("SPNConfig", 0, "SOEApproverUID");
		$SOEApproverEmail = RetrieveField("UserRecords", $SOEApproverUID, "PreferredEmail");
		$Email = RetrieveField("SPNConfig", 0, "Email4");
		$ReasonForRequest = RetrieveField("SPNRequestRecords", $SPNReqID, "Notes");

		//If no email address was found, then do nothing.
		if ($SOEApproverEmail != "") {

			//Setup the email message.
//			$EmailMessage = $Email."\r\n\r\nStudent Name:".$FirstName." ".$LastName."\r\nNetID/RUID: ".$_POST["netid"].", ".$RUID."\r\nMajor: ".$Major."\r\nCourse: 14:332:".$_POST["course"].":".$_POST["section"].", ".$_POST["title"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\nReason For Request:\r\n".$ReasonForRequest."\r\n\r\nPlease login to ECEAPPS to review the <A HREF=\"https://apps.ece.rutgers.edu/SPN/SPNReqSOECreateForm.php?spnid=".$SPNReqID."\">request</A>";
			$EmailMessage = $Email."\r\n\r\nStudent Name: ".$FirstName." ".$LastName."\r\nNetID/RUID: ".$_POST["netid"].", ".$RUID."\r\nMajor: ".$Major."\r\nCourse: 14:332:".$_POST["course"].":".$_POST["section"].", ".$_POST["title"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\nReason For Request:\r\n".$ReasonForRequest."\r\n";
			mail($SOEApproverEmail, "Non-ECE Student SPN Request", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\nReply-To: ".$ReplyToEmail);

			//Write a change record to note the fact that an email was sent.
			UpdateField("SPNRequestRecords", $SPNReqID, $UserDoingUpdateUID, $CurrentTime, "Notes", "SOE approver email sent to ".$SOEApproverEmail.".");
		}
	}

/*
	//If SOE just approved then ECE needs an alert email.
	if ( ($NewStatus == "W") && ($OldStatus != "W") ) {

		$RequestEmailUID = RetrieveField("SPNConfig", 0, "RequestEmailUID");
		$RequestEmail = RetrieveField("UserRecords", $RequestEmailUID, "PreferredEmail");

		//If no email address was found, then do nothing.
		if ($RequestEmail != "") {

			//Setup the email message.
			$EmailMessage = "Hello,\r\n\r\nA non-ECE student SPN request was just approved by SOE for ".$_POST["netid"]." for\r\n\r\nCourse: ".$_POST["course"]."\r\nSection: ".$_POST["section"]."\r\nSemester: ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]."\r\n\r\nPlease login to ECEAPPS to review the <A HREF=\"https://apps.ece.rutgers.edu/SPN/SPNReqStaffCreateForm.php?spnid=".$SPNReqID."\">request</A>.\r\n\r\nSincerely,\r\nECE SPN System";
			mail($RequestEmail, "SPN Request Approved by SOE", $EmailMessage, "From: spn@apps.ece.rutgers.edu\r\n");
		}
	}
*/

	if ($ChangeFlag == TRUE)
		echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\"><A href=\"SPNReqStaffCreateForm.php?spnid=".$SPNReqID."\">SPN Request ".$SPNReqID."</A> successfully updated.";
	else
		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Nothing changed for <A href=\"SPNReqStaffCreateForm.php?spnid=".$SPNReqID."\">SPN Request ".$SPNReqID."</A>.";

    echo " Back to <A HREF=\"SPNReqStaffCreateList.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&typecheck=".$TypeCheck."&typesearch=".$TypeSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionearch=".urlencode($SectionSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."&sort3=".urlencode($Sort3)."\">previous search</A>.</DIV>\r\n";

	//If you change this threshold, make sure to check the initialization of $RemainingSPNs - it must be bigger than the threshold.
	if ($RemainingSPNs < 5)
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">WARNING: There are only ".$RemainingSPNs." SPNs left for ".$SemesterList[$_POST["semester"]]." ".$_POST["year"]." ".$_POST["course"].":".$_POST["section"]."</DIV>\r\n";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNReqStaffProcessForm.log"); 
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
		if ($Field == "Notes")
			$SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime LIMIT 1;";
		else
			$SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
	        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve appointment field from database: ".$mysqli->error."</DIV>";
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
