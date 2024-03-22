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

    if ($UserDoingUpdateAccessRole != "D") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a student to create or edit an SPN request using this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for SPN request status dropdown list box
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
      "Section"=>"Section Number"
      );

     //This had to be done to get rid of all the undefined variable warnings.
    // Trying to preset some of the check boxes doesn't work - then you can never un-check them..
    $StatusCheck = "";
    $StatusSearch = "";
	$SemesterCheck = "";
    $SemesterSearch = "";
    $YearSearch = "";
	$CourseCheck = "";
	$CourseSearch = "";
	$SectionCheck = "";
	$SectionSearch = "";
    $Sort1 = "CreateTime";
    $Sort2 = "";

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
    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
	        $StatusCheck = $_GET["statuscheck"];

    if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "RPASNVCD", 6))
	        $StatusSearch = $_GET["statussearch"];

    if (isset($_GET["semestercheck"]))
       	if (!ValidateField("semestercheck", "checked", 7))
	        $SemesterCheck = $_GET["semestercheck"];

    if (isset($_GET["semestersearch"]))
       	if (!ValidateField("semestersearch", "SMF", 1))
	        $SemesterSearch = $_GET["semestersearch"];

    if (isset($_GET["yearsearch"]))
       	if (!ValidateField("yearsearch", "0123456789", 4))
	        $YearSearch = $_GET["yearsearch"];

    if (isset($_GET["coursecheck"]))
       	if (!ValidateField("coursecheck", "checked", 7))
	        $CourseCheck = $_GET["coursecheck"];

    if (isset($_GET["coursesearch"]))
       	if (!ValidateField("coursesearch", "0123456789", 3))
	        $CourseSearch = $_GET["coursesearch"];

    if (isset($_GET["sectioncheck"]))
       	if (!ValidateField("sectioncheck", "checked", 7))
	        $SectionCheck = $_GET["sectioncheck"];

    if (isset($_GET["sectionsearch"]))
       	if (!ValidateField("sectionsearch", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 3))
	        $SectionSearch = $_GET["sectionsearch"];

    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"SPNReqStudentCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only request status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD><TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"semestercheck\"".$SemesterCheck."> Show only semester "; DropdownListBox("semestersearch", $SemesterList, $SemesterSearch); echo "<INPUT TYPE=\"text\" NAME=\"yearsearch\" VALUE=\"".$YearSearch."\" SIZE=\"4\" MAXLENGTH=\"4\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"coursecheck\"".$CourseCheck."> Show only requests for course number <INPUT TYPE=\"text\" NAME=\"coursesearch\" VALUE=\"".$CourseSearch."\" SIZE=\"3\" MAXLENGTH=\"3\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"sectioncheck\"".$SectionCheck."> Show only requests for section number <INPUT TYPE=\"text\" NAME=\"sectionsearch\" VALUE=\"".$SectionSearch."\" SIZE=\"2\" MAXLENGTH=\"2\"></TD></TR>";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD></TR>";
    echo "</TBODY></TABLE>";

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "AND Type='U' AND UserID=".$UserDoingUpdateUID." ";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (Status='".mysqli_real_escape_string($mysqli, $StatusSearch)."') ";
    if ($SemesterCheck && $SemesterSearch)
	    $Conditions .= "AND (Semester='".mysqli_real_escape_string($mysqli, $SemesterSearch)."') ";
    if ($SemesterCheck && $YearSearch)
	    $Conditions .= "AND (Year='".mysqli_real_escape_string($mysqli, $YearSearch)."') ";
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

    //Replace the leading comma with "ORDER BY", if any sortings were selected.
    if ($Sorting)
	    $Sorting = "ORDER BY ".substr($Sorting, 2);


    //Now we can put together the final query. 
    $SQLQuery = "SELECT SPNRequests.SID, SPNRequests.CreateTime, SPNRequest.Status, SPNRequest.StatusTime, SPNRequest.UserID, SPNRequest.Type, SPNRequest.Year, SPNRequest.Semester, SPNRequest.Title, SPNRequest.Course, SPNRequest.Section, User.UID, User.NetID, User.Email FROM SPNRequests
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
				(SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Email
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

        echo $SQLResult->num_rows." SPN requests found.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>Submitted</TH><TH>Title</TH><TH>Course</TH><TH>Section</TH><TH>Semester</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            //If this items status hasn't changed in 5 days, highlight it.
            if (strspn($Fields["Status"], "R") && ($CurrentTime > $Fields["StatusTime"] + (5 * 86400)) )
                echo "<TR CLASS=\"table-danger\">";
            else
                echo "<TR>";

            echo "<TD><A HREF=\"SPNReqStudentCreateForm.php?spnid=".$Fields["SID"]."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionsearch=".urlencode($SectionSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".$StatusList[$Fields["Status"]]."</A></TD>";
            echo "<TD>".date("F j, Y, g:i a", $Fields["CreateTime"])."</TD>";
            echo "<TD>".$Fields["Title"]."</TD>";
            echo "<TD>".$Fields["Course"]."</TD>";
            echo "<TD>".$Fields["Section"]."</TD>";
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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNReqStudentCreateList.log"); 
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
			$_GET[$FormField] = "";
			return TRUE;
		}

		//Check maximum length.
		if ($FieldLength > $MaxLength) {
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
