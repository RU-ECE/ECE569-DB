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
    $Title = "Courses";
    $Menu = "Office Hours";
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

    //Extract the User ID and their access role.
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
    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "D") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit office hours.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for course assignment status dropdown list box
    $StatusList = array
      (
      ""=>"",
	  "A"=>"Active",
      "D"=>"Deleted"
      );

    $SemesterList = array
      (
      "S"=>"Spring",
	  "M"=>"Summer",
      "F"=>"Fall"
      );

    $DayList = array
      (
      "1"=>"Monday",
	  "2"=>"Tuesday",
	  "3"=>"Wednesday",
	  "4"=>"Thursday",
	  "5"=>"Friday"
      );

    $SortList = array
      (
      ""=>"",
	  "Number"=>"Course",
      "Day"=>"Day",
	  "StartTime"=>"Start Time",
	  "RoomID"=>"Room",
	  "UserID"=>"Instructor"
      );



	//Populate the courses dropdown with all the courses.
    $SQLQuery = "SELECT Courses.CID, Course.Status, Course.Number, Course.Name FROM Courses
        LEFT JOIN
            (SELECT CID,
                (SELECT Value FROM CourseRecords WHERE Field='Status' AND ID=Courses.CID ORDER BY CreateTime DESC LIMIT 1) AS Status,
                (SELECT Value FROM CourseRecords WHERE Field='Number' AND ID=Courses.CID ORDER BY CreateTime DESC LIMIT 1) AS Number,
                (SELECT Value FROM CourseRecords WHERE Field='Name' AND ID=Courses.CID ORDER BY CreateTime DESC LIMIT 1) AS Name
            FROM Courses) AS Course
        ON Courses.CID=Course.CID
        ORDER BY Course.Number;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct course list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

	//WHAT TO DO IF THIS IS A TOPICS COURSE!!! NEED SECTION NUMBER TOO!!
	//WHAT ABOUT A COURSE THAT WAS DELETED??

    //Add each course to the associative array. There are two arrays - one for the filtering, and another with all the courses.
	// Have to do this so "Retired" courses will still print properly in the office hours list.
    $CourseList[""] = "";
    while($Fields = $SQLResult->fetch_row() ) {
		$AllCourseList[$Fields[0]] = $Fields[2]." ".$Fields[3];
		if ($Fields[1] == "A")
			$CourseList[$Fields[0]] = $Fields[2]." ".$Fields[3];
	}


	//Populate the rooms dropdown with all the rooms.
    $SQLQuery = "SELECT RID, RoomName FROM Rooms ORDER BY RID;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct rooms list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Add each room to the associative array.
    $RoomList[""] = "";
    while($Fields = $SQLResult->fetch_row() )
        $RoomList[$Fields[0]] = $Fields[1];


	//Figure out the current semester and year.
	$CurrentMonth = date('n', $CurrentTime);
    if ($CurrentMonth < 5) {
        $SemesterSearch = "S";
	    $YearSearch = date('Y', $CurrentTime);
	}
    else if ( ($CurrentMonth > 4) && ($CurrentMonth < 9) ) {
        $SemesterSearch = "M";
	    $YearSearch = date('Y', $CurrentTime);
	}
    else if ($CurrentMonth > 8) {
        $SemesterSearch = "F";
	    $YearSearch = date('Y', $CurrentTime);
	}

    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.
    //Setup the default button states based on form fields 
 	$HID = "";
    if (isset($_GET["hid"]))
        if (!ValidateField("hid", "0123456789", 10))
			$HID = $_GET["hid"];

	$StatusCheck = "";
    if (isset($_GET["statuscheck"]))
	    if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

	$StatusSearch = "";
    if (isset($_GET["statussearch"]))
        if (!ValidateField("statussearch", "AD", 1))
			$StatusSearch = $_GET["statussearch"];

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

	$NameCheck = "";
    if (isset($_GET["namecheck"]))
	    if (!ValidateField("namecheck", "checked", 7))
		    $NameCheck = $_GET["namecheck"];

	$NameSearch = "";
    if (isset($_GET["namesearch"]))
       	if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ- ", 50))
   		    $NameSearch = $_GET["namesearch"];

	$CourseCheck = "";
    if (isset($_GET["coursecheck"]))
       	if (!ValidateField("coursecheck", "checked", 7))
	        $CourseCheck = $_GET["coursecheck"];

	$CourseSearch = "";
    if (isset($_GET["coursesearch"]))
       	if (!ValidateField("coursesearch", "0123456789", 10))
	        $CourseSearch = $_GET["coursesearch"];

	$DayCheck = "";
    if (isset($_GET["daycheck"]))
	    if (!ValidateField("daycheck", "checked", 7))
		    $DayCheck = $_GET["daycheck"];

	$DaySearch = "";
    if (isset($_GET["daysearch"]))
       	if (!ValidateField("daysearch", "0123456789", 1))
   		    $DaySearch = $_GET["daysearch"];

	$RoomCheck = "";
    if (isset($_GET["roomcheck"]))
	    if (!ValidateField("roomcheck", "checked", 7))
		    $RoomCheck = $_GET["roomcheck"];

	$RoomSearch = "";
    if (isset($_GET["roomsearch"]))
       	if (!ValidateField("roomsearch", "0123456789", 10))
   		    $RoomSearch = $_GET["roomsearch"];

	$Sort1 = "Day";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

	$Sort2 = "CourseID";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"HoursStudentCreateList.php\">\r\n";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>\r\n";
    echo "<TR>";
	echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only hours status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"daycheck\"".$DayCheck."> Show only day "; DropdownListBox("daysearch", $DayList, $DaySearch); echo "</TD>\r\n";
	echo "<TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TD>\r\n";
    echo "</TR>";

    echo "<TR>";
	echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"semestercheck\"".$SemesterCheck."> Show only semester "; DropdownListBox("semestersearch", $SemesterList, $SemesterSearch); echo "<INPUT TYPE=\"text\" NAME=\"yearsearch\" VALUE=\"".$YearSearch."\" SIZE=\"4\" MAXLENGTH=\"4\"></TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"roomcheck\"".$RoomCheck."> Show only room "; DropdownListBox("roomsearch", $RoomList, $RoomSearch); echo "</TD></TR>\r\n";
    echo "</TR>";

    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"namecheck\"".$NameCheck."> Show only my office hours.</TD></TR>\r\n";
    echo "<TR><TD COLSPAN=\"3\"><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"coursecheck\"".$CourseCheck."> Show only course "; DropdownListBox("coursesearch", $CourseList, $CourseSearch); echo "</TD></TR>\r\n";
    echo "<TR><TD>Sort by "; DropdownListBox("sort1", $SortList, $Sort1); echo " and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD></TR>\r\n";
    echo "</TBODY></TABLE>\r\n";

	//Check if this is a search for a specific request, and if the ID is zero.
	if ($HID == "0") {
        echo "0 Office Hours found.<BR>";
		goto CloseSQL;
	}

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (Status='".mysqli_real_escape_string($mysqli, $StatusSearch)."')";
    if ($SemesterCheck && $SemesterSearch)
	    $Conditions .= "AND (Semester='".mysqli_real_escape_string($mysqli, $SemesterSearch)."') ";
    if ($SemesterCheck && $YearSearch)
	    $Conditions .= "AND (Year='".mysqli_real_escape_string($mysqli, $YearSearch)."') ";
    if ($CourseCheck && $CourseSearch)
	    $Conditions .= "AND (CourseID='".mysqli_real_escape_string($mysqli, $CourseSearch)."') ";
    if ($DayCheck && $DaySearch)
	    $Conditions .= "AND (Day='".mysqli_real_escape_string($mysqli, $DaySearch)."') ";
    if ($RoomCheck && $RoomSearch)
	    $Conditions .= "AND (RoomID='".mysqli_real_escape_string($mysqli, $RoomSearch)."') ";
    if ($NameCheck)
	    $Conditions .= "AND (UserID='".$UserDoingUpdateUID."') ";

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
    $SQLQuery = "SELECT Hours.HID, Hour.Status, Hour.Semester, Hour.Year, Hour.UserID, Hour.CourseID, Hour.RoomID, Hour.Day, Hour.StartTime, Hour.StopTime, User.FirstName, User.LastName, User.Email, Course.Number FROM Hours
        LEFT JOIN
            (SELECT HID,
                (SELECT Value FROM HourRecords WHERE Field='Status' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS Status,
                (SELECT Value FROM HourRecords WHERE Field='Semester' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS Semester,
                (SELECT Value FROM HourRecords WHERE Field='Year' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS Year,
                (SELECT Value FROM HourRecords WHERE Field='UserID' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS UserID,
                (SELECT Value FROM HourRecords WHERE Field='CourseID' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS CourseID,
                (SELECT Value FROM HourRecords WHERE Field='RoomID' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS RoomID,
                (SELECT Value FROM HourRecords WHERE Field='Day' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS Day,
                (SELECT Value FROM HourRecords WHERE Field='StartTime' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS StartTime,
                (SELECT Value FROM HourRecords WHERE Field='StopTime' AND ID=Hours.HID ORDER BY CreateTime DESC LIMIT 1) AS StopTime
            FROM Hours) AS Hour
        ON Hours.HID=Hour.HID
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
				(SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Email
			FROM Users) AS User
		ON User.UID=Hour.UserID
		LEFT JOIN
			(SELECT CID,
				(SELECT Value FROM CourseRecords WHERE Field='Number' AND ID=Courses.CID ORDER BY CreateTime DESC LIMIT 1) AS Number
			FROM Courses) AS Course
		ON Course.CID=Hour.CourseID
 		".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get Office Hours list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    $TotalCost = 0;
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." Office Hours found.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>Semester</TH><TH>Day</TH><TH>Course</TH><TH>Instructor</TH><TH>Email</TH><TH>Room</TH><TH>Hours</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {
            echo "<TR>";
			//Students can only edit their own office hours.
			if ($UserDoingUpdateUID == $Fields["UserID"])
				echo "<TD><A HREF=\"HoursStudentCreateForm.php?hid=".$Fields["HID"]."&statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&semestercheck=".$SemesterCheck."&semestersearch=".urlencode($SemesterSearch)."&yearsearch=".urlencode($YearSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&daycheck=".$DayCheck."&daysearch=".urlencode($DaySearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".$StatusList[$Fields["Status"]]."</A></TD>";
			else
				echo "<TD>".$StatusList[$Fields["Status"]]."</TD>";
            echo "<TD>".$SemesterList[$Fields["Semester"]]." ".$Fields["Year"]."</TD>";
            echo "<TD>".$DayList[$Fields["Day"]]."</TD>";
            echo "<TD>".$AllCourseList[$Fields["CourseID"]]."</TD>";
            echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>";
            echo "<TD>".$Fields["Email"]."</TD>";
            echo "<TD>".$RoomList[$Fields["RoomID"]]."</TD>";
            echo "<TD>".gmdate("g:i a", $Fields["StartTime"])."-".gmdate("g:i a", $Fields["StopTime"])."</TD>";
            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." Office Hours found.<BR>";
    }
    else 
        echo "0 Office Hours found.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Hours/HoursStudentCreateList.log"); 
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

<!--
CREATE TABLE `Hours` (
  `HID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT
);

CREATE TABLE `HourRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO Hours (CreateTime) VALUES (1685106110);
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "Status", "A");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "UserID", "51");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "CourseID", "5");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "RoomID", "45");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "Semester", "F");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "Year", "2023");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "Day", "2");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106111, "StartTime", "36000");
INSERT INTO HourRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106111, "StopTime", "41400");

-->
