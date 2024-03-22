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
		//Not logged in, so check if a login is pending.
		if ($_SERVER["REQUEST_METHOD"] && $_GET["ticket"]) {
			//Login pending. Send the ticket back to the CAS server for verification and NetID.
			if ( ($_SESSION["netid"] = CASAuthenticateTicket($_GET["ticket"])) == "") {
				require('../template/head.php');
		        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unable to get NetID from authentication server.</DIV>";
				goto SendTailer;
			}
        }
		else {
			//Not logged in and no login pending - send them off to CAS. This script will be called again during authentication.
			header("Location: https://cas.rutgers.edu/login?service=".urlencode("https://apps.ece.rutgers.edu".$_SERVER['PHP_SELF']) );
            exit;
		}
	}

	//User is logged in. Grab the NetID.
    $UserDoingUpdateNetID = $_SESSION['netid'];

	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli->connect_errno) {
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

    //Extract the user's role. D=Student, S=ECE Staff, F=ECE Faculty, A=SOE Approver, O=External
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
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for SPN request status dropdown list box
	//"RW"=>"ECE Review",
	//"W"=>"SOE Approved",
    $StatusList = array
      (
      "R"=>"Requested",
	  "P"=>"Pending",
      "A"=>"Approved",
	  "S"=>"Resolved",
	  "N"=>"Not ECE",
	  "V"=>"SOE Review",
	  "C"=>"Canceled",
      "D"=>"Denied"
      );

    //Array of names for type dropdown list box
    $TypeList = array
      (
      "U"=>"Ugrad",
	  "G"=>"Grad",
	  "S"=>"Special Problems",
	  "I"=>"Internship"
      );

    //Array of names for semester dropdown list box
    $SemesterList = array
      (
      "S"=>"Spring",
	  "M"=>"Summer",
      "F"=>"Fall"
      );

    $SortList = array
      (
      ""=>"",
	  "CreateTime"=>"Request Created",
      "Course"=>"Course Number",
      "Section"=>"Section Number",
	  "NetID"=>"NetID"
      );


	//Figure out the current semester and year.
	// From April 1 to November 1 is Fall semester
	$CurrentMonth = date('n', $CurrentTime);
    if ( ($CurrentMonth > 3) && ($CurrentMonth < 11) ) {
        $SemesterSearch = "F";
	    $YearSearch = date('Y', $CurrentTime);
	}
    else {
	    $SemesterSearch = "S";
		if ($CurrentMonth < 4)
		    $YearSearch = date('Y', $CurrentTime);
		else
		    $YearSearch = date('Y', $CurrentTime) + 1;
	}

    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.
    //Setup the default button states based on form fields 
	$RequestID = "";
    if (isset($_GET["requestid"]))
       	if (!ValidateField("requestid", "0123456789", 5))
	        $RequestID = $_GET["requestid"];

    $StatusCheck = "";
    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
	        $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
    if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "RPASNVCD", 6))
	        $StatusSearch = $_GET["statussearch"];

    $TypeCheck = "";
    if (isset($_GET["typecheck"]))
       	if (!ValidateField("typecheck", "checked", 7))
	        $TypeCheck = $_GET["typecheck"];

    $TypeSearch = "";
    if (isset($_GET["typesearch"]))
       	if (!ValidateField("typesearch", "UGSI", 1))
	        $TypeSearch = $_GET["typesearch"];

	$SemesterCheck = "";
    if (isset($_GET["semestercheck"]))
       	if (!ValidateField("semestercheck", "checked", 7))
	        $SemesterCheck = $_GET["semestercheck"];

    //$SemesterSearch = "";
    if (isset($_GET["semestersearch"]))
       	if (!ValidateField("semestersearch", "SMF", 1))
	        $SemesterSearch = $_GET["semestersearch"];

    //$YearSearch = "";
    if (isset($_GET["yearsearch"]))
       	if (!ValidateField("yearsearch", "0123456789", 4))
	        $YearSearch = $_GET["yearsearch"];

    $NetIDCheck = "";
    if (isset($_GET["netidcheck"]))
       	if (!ValidateField("netidcheck", "checked", 7))
	        $NetIDCheck = $_GET["netidcheck"];

    $NetIDSearch = "";
    if (isset($_GET["netidsearch"]))
       	if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyz0123456789", 25))
	        $NetIDSearch = $_GET["netidsearch"];

	$CourseCheck = "";
    if (isset($_GET["coursecheck"]))
       	if (!ValidateField("coursecheck", "checked", 7))
	        $CourseCheck = $_GET["coursecheck"];

	$CourseSearch = "";
    if (isset($_GET["coursesearch"]))
       	if (!ValidateField("coursesearch", "0123456789", 3))
	        $CourseSearch = $_GET["coursesearch"];

	$SectionCheck = "";
    if (isset($_GET["sectioncheck"]))
       	if (!ValidateField("sectioncheck", "checked", 7))
	        $SectionCheck = $_GET["sectioncheck"];

	$SectionSearch = "";
    if (isset($_GET["sectionsearch"]))
       	if (!ValidateField("sectionsearch", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 3))
	        $SectionSearch = $_GET["sectionsearch"];

    $Sort1 = "CreateTime";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];

	$Sort3 = "";
    if (isset($_GET["sort3"]))
       	if (!ValidateField("sort3", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort3 = $_GET["sort3"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"SPNReqStaffCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only request status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD><TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"typecheck\"".$TypeCheck."> Show only type "; DropdownListBox("typesearch", $TypeList, $TypeSearch); echo "</TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"semestercheck\"".$SemesterCheck."> Show only semester "; DropdownListBox("semestersearch", $SemesterList, $SemesterSearch); echo "<INPUT TYPE=\"text\" NAME=\"yearsearch\" VALUE=\"".$YearSearch."\" SIZE=\"4\" MAXLENGTH=\"4\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\"".$NetIDCheck."> Show only requests for NetID <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"25\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"coursecheck\"".$CourseCheck."> Show only requests for course number <INPUT TYPE=\"text\" NAME=\"coursesearch\" VALUE=\"".$CourseSearch."\" SIZE=\"3\" MAXLENGTH=\"3\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"sectioncheck\"".$SectionCheck."> Show only requests for section number <INPUT TYPE=\"text\" NAME=\"sectionsearch\" VALUE=\"".$SectionSearch."\" SIZE=\"2\" MAXLENGTH=\"2\"></TD></TR>";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "and then by "; DropdownListBox("sort3", $SortList, $Sort3);echo "</TD></TR>";
    echo "</TBODY></TABLE>";

	//Check if this is a search for a specific request, and if the ID is zero.
	if ($RequestID == "0") {
        echo "0 SPN requests found.<BR>";
		goto CloseSQL;
	}

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND LOCATE(Status,'".mysqli_real_escape_string($mysqli, $StatusSearch)."') ";
    if ($TypeCheck && $TypeSearch)
	    $Conditions .= "AND (Type='".mysqli_real_escape_string($mysqli, $TypeSearch)."') ";
    if ($SemesterCheck && $SemesterSearch)
	    $Conditions .= "AND (Semester='".mysqli_real_escape_string($mysqli, $SemesterSearch)."') ";
    if ($SemesterCheck && $YearSearch)
	    $Conditions .= "AND (Year='".mysqli_real_escape_string($mysqli, $YearSearch)."') ";
    if ($NetIDCheck && $NetIDSearch)
	    $Conditions .= "AND (NetID='".mysqli_real_escape_string($mysqli, $NetIDSearch)."') ";
    if ($CourseCheck && $CourseSearch)
	    $Conditions .= "AND (Course='".mysqli_real_escape_string($mysqli, $CourseSearch)."') ";
    if ($SectionCheck && $SectionSearch)
	    $Conditions .= "AND (Section='".mysqli_real_escape_string($mysqli, $SectionSearch)."') ";

    //Replace the leading "AND" with "WHERE", if any conditions were selected.
    if ($Conditions)
	    $Conditions = "WHERE ".substr($Conditions, 4);

    //Compose the sortings string.
    if ($Sort1)
        $Sorting .= ", ".$Sort1;
    if ($Sort2)
        $Sorting .= ", ".$Sort2;
    if ($Sort3)
        $Sorting .= ", ".$Sort3;

    //Replace the leading comma with "ORDER BY", if any sortings were selected.
    if ($Sorting)
	    $Sorting = "ORDER BY ".substr($Sorting, 2);


    //Now we can put together the final query. 
    $SQLQuery = "SELECT SPNRequests.SID, SPNRequests.CreateTime, SPNRequest.Status, SPNRequest.StatusTime, SPNRequest.UserID, SPNRequest.Type, SPNRequest.Year, SPNRequest.Semester, SPNRequest.Title, SPNRequest.Course, SPNRequest.Section, User.UID, User.NetID, User.RUID, User.Major, User.Email, User.FirstName, User.LastName FROM SPNRequests
        LEFT JOIN
            (SELECT SID,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Status' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Status,
                (SELECT CreateTime FROM SPNRequestRecords WHERE Field='Status' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS StatusTime,
                (SELECT Value FROM SPNRequestRecords WHERE Field='UserID' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS UserID,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Type' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Type,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Year' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Year,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Semester' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Semester,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Title' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Title,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Course' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Course,
                (SELECT Value FROM SPNRequestRecords WHERE Field='Section' AND ID=SPNRequests.SID ORDER BY CreateTime DESC LIMIT 1) AS Section
            FROM SPNRequests) AS SPNRequest
        ON SPNRequests.SID=SPNRequest.SID
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
				(SELECT Value FROM UserRecords WHERE Field='Major' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Major,
				(SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Email,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName
			FROM Users) AS User
		ON User.UID=SPNRequest.UserID
 		".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get SPN request list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    $TotalCost = 0;
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." SPN requests found. <A HREF=\"SPNReqStaffCreateExport.php?statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&typecheck=".$TypeCheck."&typesearch=".$TypeSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionsearch=".urlencode($SectionSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."&sort3=".urlencode($Sort3)."\">Export</A> this list.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>Type</TH><TH>Submitted</TH><TH>Name/RUID/NetID</TH><TH>Email</TH><TH>Title</TH><TH>Course Section</TH><TH>Semester</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            //If this items status hasn't changed in 14 days, highlight it.
            if (strspn($Fields["Status"], "R") && ($CurrentTime > $Fields["StatusTime"] + (14 * 86400)) )
                echo "<TR CLASS=\"table-danger\">";
            else
                echo "<TR>";

            echo "<TD><A HREF=\"SPNReqStaffCreateForm.php?spnid=".$Fields["SID"]."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&typecheck=".$TypeCheck."&typesearch=".$TypeSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionsearch=".urlencode($SectionSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."&sort3=".urlencode($Sort3)."\">".$StatusList[$Fields["Status"]]."</A></TD>";
            echo "<TD>".$TypeList[$Fields["Type"]]." ".$Fields["Major"]."</TD>";
            echo "<TD>".date("F j, Y, g:i a", $Fields["CreateTime"])."</TD>";
            echo "<TD><A HREF=\"../Users/UsersCreateForm.php?userid=".$Fields["UserID"]."\">".$Fields["NetID"]."</A>, ".$Fields["RUID"].", ".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>";
            echo "<TD>".$Fields["Email"]."</TD>";
            echo "<TD>".$Fields["Title"]."</TD>";
            echo "<TD>".$Fields["Course"].":".$Fields["Section"]."</TD>";
            echo "<TD>".$SemesterList[$Fields["Semester"]]." ".$Fields["Year"]."</TD>";

            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." SPN requests found.<BR>";
    }
    else 
        echo "0 SPN requests found.<BR>";

    echo "</FORM>";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNReqStaffCreateList.log"); 
    }

    //Function to create a dropdown list box.
    function DropdownListBox($Name, $Items, $Selection) {

        echo "<SELECT NAME=\"".$Name."\">\r\n";
        foreach ($Items as $Key => $Value) {
		    if ($Key == $Selection)
			    echo "<OPTION VALUE=\"".$Key."\" SELECTED>".$Value."</OPTION>\r\n";
		    else
			    echo "<OPTION VALUE=\"".$Key."\">".$Value."</OPTION>\r\n";
	    }
	    echo "</SELECT>\r\n";
	    return 0;
    }

	//Validates a form field.
	function ValidateField($FormField, $ValidChars, $MaxLength) {

	    if (isset($_GET[$FormField])) {
			$FieldValue = $_GET[$FormField];
			$FieldLength = strlen($FieldValue);
		}
		else {
			$FieldValue = "";
			$FieldLength = 0;
		}

		//If the field is blank, do nothing. 
		if ($FieldLength == 0)
			return FALSE;

		//Check for illegal characters.
		if ( ($CharPosition = strspn($FieldValue, $ValidChars)) < $FieldLength) {
			WriteLog("ValidateField() Illegal Character at ".$CharPosition." of ".$_GET[$FormField]);
			$_GET[$FormField] = "";
			return TRUE;
		}

		//Check maximum length.
		if ($FieldLength > $MaxLength) {
			WriteLog("ValidateField() Field too long ".$FieldLength." of ".$_GET[$FormField]);
			$_GET[$FormField] = "";
			return TRUE;
		}

		return FALSE;
    }

    //Returns the NetID of the authenticated user, or empty string on error.
    function CASAuthenticateTicket($Ticket) {

        //This has to match the entire URL of the requested page.
        $casGet = "https://cas.rutgers.edu/serviceValidate?ticket=".$Ticket."&service=".urlencode("https://apps.ece.rutgers.edu".$_SERVER['PHP_SELF']);
        $response = file_get_contents($casGet);
        if (preg_match('/cas:authenticationSuccess/', $response)) {
            $str2split = preg_replace("/\W/", '', $response);
            $vals = explode('casuser', $str2split);
            return $vals[1];
        }
		else {
			WriteLog("CASAuthenticateTicket() Failure: ".$response);
            return "";
		}
    }
?>

<!--
CREATE TABLE `SPNRequests` (
  `SID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT
);

CREATE TABLE `SPNRequestRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO SPNRequests (CreateTime) VALUES (1644439134);
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Status", "R");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Type", "U");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "UserID", "51");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Title", "Principles I");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Course", "221");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Section", "02");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Year", "2023");
INSERT INTO SPNRequestRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Semester", "S");

INSERT INTO SPNConfig(ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Email1", "Dear XXX");

Email1: Your request for an SPN has been approved and is shown below.
Email2: Unfortunately, there is no additional space available for the course shown below. Space sometimes becomes available in the first few weeks of the semester as some students drop the course, so try to register periodically during that time.
Email3: Although there is currently no additional space available in the course you requested below, we are working to increase the capacity of the course. If successful, you will receive another email with an SPN.
pamela.heinold@rutgers.edu

SPNReqStaffCreateList.php
SPNReqCreateForm.php
SPNReqProcessForm.php
SPNReqStudentCreateList.php
SPNCreateForm.php
SPNProcessForm.php
SPNCreateList.php

8/16/2023 Notes
Special Problems SPN requests. Need to upload a description.
Internship SPN requests. There is a Canvas site with a form that needs to be moved here. Sasan gave me access.
The form needs to upload some files.
The reviewer needs to be able to see the files.
There needs to be another category of SPN - currently I have undergrad and grad.
Capstone SPNs also discussed. Next time there will be only one giant section.
Students will put in SPN requests, or SPNs will be automatically emailed once teams are formed/approved?
Need to restrict teams to 2 min, 5 max students.

update SPNRequestRecords set Field='Type' where Field='Degree';
alter table SPNs change Degree Type VARCHAR(1);

-->
