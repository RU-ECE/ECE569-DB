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

	//This is needed later for getting the current year and spring/fall semester.
	$CurrentTime = time();

    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Access Control.
    //Students only for this version of the page.
    if ($UserDoingUpdateAccessRole != "D") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit office hours.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a student to create or edit office hours using this page.</DIV>";
		goto CloseSQL;
    }
 
    //Array of names for course assignment status dropdown list box
    $StatusList = array
      (
      "A"=>"Active",
      "D"=>"Deleted"
      );

    //Array of names for semester dropdown list box
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


	//Populate the courses dropdown with all the courses.
    $SQLQuery = "SELECT Courses.CID, Course.Number, Course.Name FROM Courses
        LEFT JOIN
            (SELECT CID,
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

    //Add each course to the associative array.
    $CourseList[""] = "";
    while($Fields = $SQLResult->fetch_row() )
        $CourseList[$Fields[0]] = $Fields[1]." ".$Fields[2];


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


    //Initialize all the form data variables. These will be filled in later if an Hours ID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.
	$Status = "";
	$Semester = "";
	$Year = "";
//	$NetID = "";
	$RoomID = "";
	$CourseID = "";
	$Course = "";
	$Day = "";
	$StartHour = "";
	$StartMinute = "";
	$StartAM = "checked";
	$StartPM = "";
	$StopHour = "";
	$StopMinute = "";
	$StopAM = "checked";
	$StopPM = "";


    $HID = 0;
    if (isset($_GET["hid"]))
       	if (!ValidateField("hid", "0123456789", 10))
	        $HID = $_GET["hid"];


	//Figure out the current semester and year.
	$CurrentMonth = date('n', $CurrentTime);
    if ($CurrentMonth < 5) {
        $Semester = "S";
	    $Year = date('Y', $CurrentTime);
	}
    else if ( ($CurrentMonth > 4) && ($CurrentMonth < 9) ) {
        $Semester = "M";
	    $Year = date('Y', $CurrentTime);
	}
    else if ($CurrentMonth > 8) {
        $Semester = "F";
	    $Year = date('Y', $CurrentTime);
	}

	//Consolidated "Back to previous search" handling.
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

	$SemesterSearch = "";
    if (isset($_GET["semestersearch"]))
       	if (!ValidateField("semestersearch", "SMF", 1))
   		    $SemesterSearch = $_GET["semestersearch"];

	$YearSearch = "";
    if (isset($_GET["yearsearch"]))
       	if (!ValidateField("yearsearch", "0123456789", 4))
   		    $YearSearch = $_GET["yearsearch"];

	$NameCheck = "";
    if (isset($_GET["namecheck"]))
	    if (!ValidateField("namecheck", "checked", 7))
		    $NameCheck = $_GET["namecheck"];

	$NameSearch = "";
    if (isset($_GET["namesearch"]))
       	if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyz0123456789", 20))
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
       	if (!ValidateField("roomsearch", "0123456789", 1))
   		    $RoomSearch = $_GET["roomsearch"];

	$Sort1 = "Day";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

	$Sort2 = "CourseID";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //If the Hours ID is specified, get all the fields for this Office Hour. 
    if ($HID != 0) {

		$SQLQuery = "SELECT Hours.HID, Hours.CreateTime, Hour.Status, Hour.Semester, Hour.Year, Hour.UserID, Hour.CourseID, Hour.RoomID, Hour.Day, Hour.StartTime, Hour.StopTime FROM Hours
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
			WHERE Hours.HID=".$HID.";";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get office hours list: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Save the fields.
		$Fields = $SQLResult->fetch_assoc();
		$Status = $Fields["Status"];
		$Semester = $Fields["Semester"];
		$Year = $Fields["Year"];
//		$NetID = $Fields["NetID"];
		$CourseID = $Fields["CourseID"];
		$RoomID = $Fields["RoomID"];
		$Day = $Fields["Day"];

		//Figure out the start and stop time fields.
		//Start/Stop times are in seconds since midnight.
		$StartHour =  (int)($Fields["StartTime"] / 3600);
		$StartMinute = (int)(($Fields["StartTime"] % 3600) / 60);
		if ($StartHour >= 12) {
			$StartAM = "";
			$StartPM = "CHECKED";
		}
		else {
			$StartAM = "CHECKED";
			$StartPM = "";
		}
		if ($StartHour > 12)
			$StartHour -= 12;

		$StopHour =  (int)($Fields["StopTime"] / 3600);
		$StopMinute = (int)(($Fields["StopTime"] % 3600) / 60);
		if ($StopHour >= 12) {
			$StopAM = "";
			$StopPM = "CHECKED";
		}
		else {
			$StopAM = "CHECKED";
			$StopPM = "";
		}
		if ($StopHour > 12)
			$StopHour -= 12;
    }

	//Make sure these hours are for this particular user. Students can only modify their own office hours.
    if ($HID != 0) {
		if ($UserDoingUpdateUID != $Fields["UserID"]) {
			WriteLog("UserID ".$UserDoingUpdateUID." trying to edit office hours for UserID ".$Fields["UserID"]);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You can only edit your own office hours.</DIV>";
			goto CloseSQL;
		}
	}

    //Send back the office hours page..
    echo "<FORM METHOD=\"POST\" ACTION=\"HoursStudentProcessForm.php\">";

	//The create/update button.
    if ($HID != 0)
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Office Hour\"> Back to <A HREF=\"HoursStudentCreateList.php?statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&semestercheck=".$SemesterCheck."&semestersearch=".urlencode($SemesterSearch)."&yearsearch=".urlencode($YearSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&daycheck=".$DayCheck."&daysearch=".urlencode($DaySearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A></P>";
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Office Hour\"></P>";

    echo "<P>";
	echo "<STRONG>Status:</STRONG><BR>"; DropdownListBox("status", $StatusList, $Status); echo "<BR>\r\n";
    echo "<STRONG>Semester:</STRONG><BR>"; DropdownListBox("semester", $SemesterList, $Semester); echo "<BR>\r\n";
    echo "<STRONG>Year:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"year\" VALUE=\"".$Year."\" SIZE=\"4\" MAXLENGTH=\"4\"><BR>\r\n";
//    echo "<STRONG>NetID:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"netid\" VALUE=\"".$NetID."\" SIZE=\"10\" MAXLENGTH=\"20\"><BR>\r\n";
    echo "<STRONG>Course:</STRONG><BR>"; DropdownListBox("courseid", $CourseList, $CourseID); echo "<BR>\r\n";
    echo "<STRONG>Room:</STRONG><BR>"; DropdownListBox("roomid", $RoomList, $RoomID); echo "<BR>\r\n";
    echo "<STRONG>Day:</STRONG><BR>"; DropdownListBox("day", $DayList, $Day); echo "<BR>\r\n";
    echo "<STRONG>Start Time: (Hour:Minute)</STRONG><BR><INPUT TYPE=\"text\" NAME=\"starthour\" VALUE=\"".$StartHour."\" SIZE=\"2\" MAXLENGTH=\"2\"> :";
	echo " <INPUT TYPE=\"text\" NAME=\"startminute\" VALUE=\"".sprintf('%02d', $StartMinute)."\" SIZE=\"2\" MAXLENGTH=\"2\">";
	echo " <INPUT TYPE=\"radio\" NAME=\"startampm\" VALUE=\"am\" ".$StartAM."> AM";
	echo " <INPUT TYPE=\"radio\" NAME=\"startampm\" VALUE=\"pm\" ".$StartPM."> PM<BR>\r\n";
    echo "<STRONG>Stop Time: (Hour:Minute)</STRONG><BR><INPUT TYPE=\"text\" NAME=\"stophour\" VALUE=\"".$StopHour."\" SIZE=\"2\" MAXLENGTH=\"2\"> :";
	echo " <INPUT TYPE=\"text\" NAME=\"stopminute\" VALUE=\"".sprintf('%02d', $StopMinute)."\" SIZE=\"2\" MAXLENGTH=\"2\">";
	echo " <INPUT TYPE=\"radio\" NAME=\"stopampm\" VALUE=\"am\" ".$StopAM."> AM";
	echo " <INPUT TYPE=\"radio\" NAME=\"stopampm\" VALUE=\"pm\" ".$StopPM."> PM<BR>\r\n";
    echo "</P>";


	//Embed all the search fields so we can make a "return to search page" after the update is processed.
    if ($HID != 0) {
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"      VALUE=\"".$StatusCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"     VALUE=\"".$StatusSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestercheck\"    VALUE=\"".$SemesterCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestersearch\"   VALUE=\"".$SemesterSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"yearsearch\"       VALUE=\"".$YearSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"namecheck\"        VALUE=\"".$NameCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"namesearch\"       VALUE=\"".$NameSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"coursecheck\"      VALUE=\"".$CourseCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"coursesearch\"     VALUE=\"".$CourseSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"daycheck\"         VALUE=\"".$DayCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"daysearch\"        VALUE=\"".$DaySearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"roomcheck\"        VALUE=\"".$RoomCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"roomsearch\"       VALUE=\"".$RoomSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"            VALUE=\"".$Sort1."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"            VALUE=\"".$Sort2."\">\r\n";
	}

    //Notes are just another row in the change record table.
    if ($HID != 0) {

        //Get all the notes for this office hours, sorted by timestamp.
        $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM HourRecords AS T1 WHERE ID=".$HID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up office hour notes ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing notes: ".$mysqli->error."</DIV>";
            goto CloseForm;
	    }

	    //Display the user query results in a table if any rows were found.
	    if ($SQLResult->num_rows > 0) {

		    echo "<P><STRONG>Notes and Change Log</STRONG><BR><TEXTAREA NAME=\"notes\" ROWS=\"10\" COLS=\"80\" readonly>";

            $ChangeTime = "";
            $ChangeUser = "";

		    while($Fields = $SQLResult->fetch_row() ) {

                //Spit out the change header only if this isn't the same user and timestamp.
                if ( ($Fields[1] != $ChangeTime) || ($Fields[4] != $ChangeUser) ) {
                    echo date("\r\nD F j, Y, g:i a", $Fields[1]).", User: ".$Fields[4]."\r\n";
                    $ChangeTime = $Fields[1];
                    $ChangeUser = $Fields[4];
                }

                //Output the change depending on the field - for notes just spit out the text - everything else it's " X changed to Y".
                if ($Fields[2] == "Notes")
			        echo "Additional note was added:\r\n".$Fields[3]."\r\n";
                else if ($Fields[2] == "Status")
                    echo " Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "CourseID")
                    echo " Course changed to ".$CourseList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "RoomID")
                    echo " Room changed to ".$RoomList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Semester")
                    echo " Semester changed to ".$SemesterList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Day")
                    echo " Day changed to ".$DayList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "StartTime")
                    echo " Start Time changed to ".gmdate("g:i a", $Fields[3])."\r\n";
                else if ($Fields[2] == "StopTime")
                    echo " Stop Time changed to ".gmdate("g:i a", $Fields[3])."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>New Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";


    //The create/update button.
    if ($HID != 0) {
        //If this is an existing office hour store the HoursID in a hidden field.
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"hid\" VALUE=\"".$HID."\">\r\n";
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Office Hour\"></P>";
    }
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Office Hour\"></P>";


CloseForm:
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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Hours/HoursStudentCreateForm.log"); 
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

