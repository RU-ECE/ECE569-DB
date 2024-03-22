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
    //SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='wine' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;
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

    //Extract the user's role. S=Student, E=ECE Staff, F=ECE Faculty, A=SOE Approver, O=External
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
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to view users.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for user status dropdown list box
   $StatusList = array
      (
	  ""=>"",
      "A"=>"Active",
      "G"=>"Graduated",
	  "L"=>"Departed",
      "S"=>"Sabbatical",
      "R"=>"Retired",
	  "E"=>"External",
      "D"=>"Deleted"
      );

    $StudentTypeList = array
      (
	  ""=>"",
      "U"=>"Undergrad",
	  "MWPN"=>"All Grad",
      "M"=>"MS/Thesis",
      "W"=>"MS",
      "P"=>"PhD",
      "N"=>"Non-Matric"
      );

    $EmployeeTypeList = array
      (
	  ""=>"",
      "S"=>"Staff",
      "FAN"=>"All Faculty",
      "F"=>"Tenured Faculty",
	  "A"=>"Adjunct Faculty",
	  "N"=>"Non-Tenure Faculty",
      "TG"=>"TA/GA",
      "T"=>"TA",
      "G"=>"GA",
      "W"=>"Work/Study",
      "P"=>"Postdoc",
      "E"=>"Fellow",
      "R"=>"Research",
      "H"=>"Hourly"
      );

    $TrackList = array
      (
	  ""=>"",
      "A"=>"CE",
      "B"=>"EE"
      );

    $GenderList = array
      (
	  ""=>"",
      "M"=>"M",
      "F"=>"F",
      "Z"=>"O"
      );

    $SortList = array
      (
      ""=>"",
      "LastName"=>"Last Name",
      "OfficialEmail"=>"Email Address",
      "RUID"=>"RU ID",
      "NetID"=>"NetID",
      "StudentType"=>"Student Type",
      "EmployeeType"=>"Employee Type",
      "UserStatus"=>"User Status",
      "DepartureDateUTC"=>"Graduation Date"
      );


    //Validate all the search option fields.
 	$UserIDSearch = "";
    if (isset($_GET["userid"]))
	    if (!ValidateField("userid", "0123456789", 10))
            $UserIDSearch = $_GET["userid"];

    $StatusCheck = "";
    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
    if (isset($_GET["statussearch"]))
	    if (!ValidateField("statussearch", "AGLSRED", 10))
			$StatusSearch = $_GET["statussearch"];

    $NetIDCheck = "";
    if (isset($_GET["netidcheck"]))
       	if (!ValidateField("netidcheck", "checked", 7))
		    $NetIDCheck = $_GET["netidcheck"];

    $NetIDSearch = "";
    if (isset($_GET["netidsearch"]))
        if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 20))
    		$NetIDSearch = $_GET["netidsearch"];

    $RUIDCheck = "";
    if (isset($_GET["ruidcheck"]))
       	if (!ValidateField("ruidcheck", "checked", 7))
		    $RUIDCheck = $_GET["ruidcheck"];

    $RUIDSearch = "";
    if (isset($_GET["ruidsearch"]))
        if (!ValidateField("ruidsearch", "0123456789", 10))
    		$RUIDSearch = $_GET["ruidsearch"];

    $NameCheck = "";
    if (isset($_GET["namecheck"]))
       	if (!ValidateField("namecheck", "checked", 7))
		    $NameCheck = $_GET["namecheck"];

    $NameSearch = "";
    if (isset($_GET["namesearch"]))
	    if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 50))
            $NameSearch = $_GET["namesearch"];

    $GenderCheck = "";
    if (isset($_GET["gendercheck"]))
       	if (!ValidateField("gendercheck", "checked", 7))
		    $GenderCheck = $_GET["gendercheck"];

    $GenderSearch = "";
    if (isset($_GET["gendersearch"]))
	    if (!ValidateField("gendersearch", "MFZ", 10))
			$GenderSearch = $_GET["gendersearch"];

    $ClassCheck = "";
    if (isset($_GET["classcheck"]))
       	if (!ValidateField("classcheck", "checked", 7))
		    $ClassCheck = $_GET["classcheck"];

    $ClassSearch = "";
    if (isset($_GET["classsearch"]))
	    if (!ValidateField("classsearch", "0123456789", 10))
			$ClassSearch = $_GET["classsearch"];

    $StudentTypeCheck = "";
    if (isset($_GET["studenttypecheck"]))
       	if (!ValidateField("studenttypecheck", "checked", 7))
		    $StudentTypeCheck = $_GET["studenttypecheck"];

    $StudentTypeSearch = "";
    if (isset($_GET["studenttypesearch"]))
	    if (!ValidateField("studenttypesearch", "UMWPN", 10))
			$StudentTypeSearch = $_GET["studenttypesearch"];

    $EmployeeTypeCheck = "";
    if (isset($_GET["employeetypecheck"]))
       	if (!ValidateField("employeetypecheck", "checked", 7))
		    $EmployeeTypeCheck = $_GET["employeetypecheck"];

    $EmployeeTypeSearch = "";
    if (isset($_GET["employeetypesearch"]))
	    if (!ValidateField("employeetypesearch", "SFANTGWPERH", 10))
			$EmployeeTypeSearch = $_GET["employeetypesearch"];

    $MajorCheck = "";
    if (isset($_GET["majorcheck"]))
       	if (!ValidateField("majorcheck", "checked", 7))
		    $MajorCheck = $_GET["majorcheck"];

    $MajorSearch = "";
    if (isset($_GET["majorsearch"]))
	    if (!ValidateField("majorsearch", "0123456789", 3))
			$MajorSearch = $_GET["majorsearch"];

    $TrackCheck = "";
    if (isset($_GET["trackcheck"]))
       	if (!ValidateField("trackcheck", "checked", 7))
		    $TrackCheck = $_GET["trackcheck"];

    $TrackSearch = "";
    if (isset($_GET["tracksearch"]))
	    if (!ValidateField("tracksearch", "AB", 10))
			$TrackSearch = $_GET["tracksearch"];

    $JudgeCheck = "";
    if (isset($_GET["judgecheck"]))
       	if (!ValidateField("judgecheck", "checked", 7))
		    $JudgeCheck = $_GET["judgecheck"];

    $AdvisorCheck = "";
    if (isset($_GET["advisorcheck"]))
       	if (!ValidateField("advisorcheck", "checked", 7))
		    $AdvisorCheck = $_GET["advisorcheck"];

    $MissingCheck = "";
    if (isset($_GET["missingcheck"]))
       	if (!ValidateField("missingcheck", "checked", 7))
		    $MissingCheck = $_GET["missingcheck"];

    $Sort1 = "LastName";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"UsersCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\" ".$StatusCheck."> Show only "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo " users.</TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"studenttypecheck\" ".$StudentTypeCheck."> Show only "; DropdownListBox("studenttypesearch", $StudentTypeList, $StudentTypeSearch); echo " students.</TD>\r\n";
    echo "<TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TD>";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\" ".$NetIDCheck."> Show only users with NetID <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"10\"></TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"employeetypecheck\" ".$EmployeeTypeCheck."> Show only "; DropdownListBox("employeetypesearch", $EmployeeTypeList, $EmployeeTypeSearch); echo " employees.</TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"ruidcheck\" ".$RUIDCheck."> Show only users with RUID <INPUT TYPE=\"text\" NAME=\"ruidsearch\" VALUE=\"".$RUIDSearch."\" SIZE=\"10\" MAXLENGTH=\"10\"></TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"majorcheck\" ".$MajorCheck."> Show only Department/Major <INPUT TYPE=\"text\" NAME=\"majorsearch\" VALUE=\"".$MajorSearch."\" SIZE=\"3\" MAXLENGTH=\"3\"></TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"namecheck\" ".$NameCheck."> Show only users with first or last name <INPUT TYPE=\"text\" NAME=\"namesearch\" VALUE=\"".$NameSearch."\" SIZE=\"15\" MAXLENGTH=\"30\"></TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"trackcheck\" ".$TrackCheck."> Show only "; DropdownListBox("tracksearch", $TrackList, $TrackSearch); echo " track.</TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"gendercheck\" ".$GenderCheck."> Show only "; DropdownListBox("gendersearch", $GenderList, $GenderSearch); echo " gender.</TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"judgecheck\" ".$JudgeCheck."> Show only possible judges.</TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"classcheck\" ".$ClassCheck."> Show only class year <INPUT TYPE=\"text\" NAME=\"classsearch\" VALUE=\"".$ClassSearch."\" SIZE=\"4\" MAXLENGTH=\"4\"></TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"advisorcheck\" ".$AdvisorCheck."> Show only capstone advisors.</TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD>\r\n";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"missingcheck\" ".$MissingCheck."> Show only missing students.</TD>\r\n";
    echo "</TR>";
    echo "</TBODY></TABLE>";

	//If we are searching for a particular user, and it's the zero user, then find nothing.
	if ($UserIDSearch == "0") {
		echo "0 users found.";
		echo "</FORM>";
		goto CloseSQL;
	}

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($NameCheck && $NameSearch)
	    $Conditions .= "AND ((LastName LIKE '%".mysqli_real_escape_string($mysqli, $NameSearch)."%') OR (FirstName LIKE '%".mysqli_real_escape_string($mysqli, $NameSearch)."%'))";
    if ($NetIDCheck && $NetIDSearch)
	    $Conditions .= "AND (NetID='".mysqli_real_escape_string($mysqli, $NetIDSearch)."') ";
    if ($RUIDCheck && $RUIDSearch)
	    $Conditions .= "AND (RUID='".mysqli_real_escape_string($mysqli, $RUIDSearch)."') ";
    if ($ClassCheck && $ClassSearch) {
        //To compare the graduation date in UTC, we need the UTC of the first and last second of the search year.
        $StartOfYear = mktime(0, 0, 0, 1, 1, $ClassSearch);
        $EndOfYear = mktime(23, 59, 59, 12, 31, $ClassSearch);
	    $Conditions .= "AND (DepartureDateUTC BETWEEN ".$StartOfYear." AND ".$EndOfYear.") ";
    }
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (UserStatus='".mysqli_real_escape_string($mysqli, $StatusSearch)."') ";
	//LOCATE() has issues with blank or null strings.
    if ($StudentTypeCheck && $StudentTypeSearch)
	    $Conditions .= "AND (StudentType<>'') AND LOCATE(StudentType,'".mysqli_real_escape_string($mysqli, $StudentTypeSearch)."') ";
    if ($EmployeeTypeCheck && $EmployeeTypeSearch)
	    $Conditions .= "AND (EmployeeType<>'') AND LOCATE(EmployeeType,'".mysqli_real_escape_string($mysqli, $EmployeeTypeSearch)."') ";
    if ($MajorCheck && $MajorSearch)
	    $Conditions .= "AND (Major='".mysqli_real_escape_string($mysqli, $MajorSearch)."') ";
    if ($TrackCheck && $TrackSearch)
	    $Conditions .= "AND (Track='".mysqli_real_escape_string($mysqli, $TrackSearch)."') ";
    if ($GenderCheck && $GenderSearch)
	    $Conditions .= "AND (Gender='".mysqli_real_escape_string($mysqli, $GenderSearch)."') ";
    if ($JudgeCheck)
	    $Conditions .= "AND (PotentialJudge='T') ";
    if ($AdvisorCheck)
	    $Conditions .= "AND (CapstoneAdvisor='T') ";
    if ($MissingCheck)
	    $Conditions .= "AND (MissingFlag='T') ";
    //More conditions go here...

    //Replace the leading "AND" with "WHERE", if any conditions were selected.
    if ($Conditions)
	    $Conditions = "WHERE ".substr($Conditions, 4);

    //Compose the sortings string.
    if ($Sort1)
        $Sorting .= ", ".$Sort1;
    if ($Sort2)
        $Sorting .= ", ".$Sort2;
    //More sortings go here...

    //Replace the leading comma with "ORDER BY", if any sortings were selected.
    if ($Sorting)
	    $Sorting = "ORDER BY ".substr($Sorting, 2);

    //Now we can put together the final query. 
    $SQLQuery = "SELECT Users.UID, Users.MissingFlag, User.NetID, User.RUID, User.UserStatus, User.FirstName, User.LastName, User.Gender, User.OfficialEmail, User.DepartureDateUTC, User.StudentType, User.EmployeeType, User.Major, User.Track, User.PotentialJudge, User.CapstoneAdvisor FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
                (SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='Gender' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Gender,
                (SELECT Value FROM UserRecords WHERE Field='OfficialEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS OfficialEmail,
                (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC,
                (SELECT Value FROM UserRecords WHERE Field='StudentType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS StudentType,
                (SELECT Value FROM UserRecords WHERE Field='EmployeeType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS EmployeeType,
                (SELECT Value FROM UserRecords WHERE Field='Major' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Major,
                (SELECT Value FROM UserRecords WHERE Field='Track' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Track,
                (SELECT Value FROM UserRecords WHERE Field='PotentialJudge' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PotentialJudge,
                (SELECT Value FROM UserRecords WHERE Field='CapstoneAdvisor' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS CapstoneAdvisor
            FROM Users) AS User
        ON Users.UID=User.UID
        ".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get users list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." users found. <A HREF=\"UsersCreateExport.php?statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&ruidcheck=".$RUIDCheck."&ruidsearch=".urlencode($RUIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&gendercheck=".$GenderCheck."&gendersearch=".urlencode($GenderSearch)."&classcheck=".$ClassCheck."&classsearch=".urlencode($ClassSearch)."&studenttypecheck=".$StudentTypeCheck."&studenttypesearch=".urlencode($StudentTypeSearch)."&employeetypecheck=".$EmployeeTypeCheck."&employeetypesearch=".urlencode($EmployeeTypeSearch)."&majorcheck=".$MajorCheck."&majorsearch=".urlencode($MajorSearch)."&trackcheck=".$TrackCheck."&tracksearch=".urlencode($TrackSearch).
			"&judgecheck=".$JudgeCheck."&advisorcheck=".$AdvisorCheck."&missingcheck=".$MissingCheck."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">Export</A> this list.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>NetID</TH><TH>Full Name</TH><TH>Gen</TH><TH>Email</TH><TH>Departure</TH><TH>Type</TH></TH><TH>Dept</TH></TR>\r\n";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            echo "<TR>";
			echo "<TD><A HREF=\"UsersCreateForm.php?userid=".$Fields["UID"]."&statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&ruidcheck=".$RUIDCheck."&ruidsearch=".urlencode($RUIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&gendercheck=".$GenderCheck."&gendersearch=".urlencode($GenderSearch)."&classcheck=".$ClassCheck."&classsearch=".urlencode($ClassSearch)."&studenttypecheck=".$StudentTypeCheck."&studenttypesearch=".urlencode($StudentTypeSearch)."&employeetypecheck=".$EmployeeTypeCheck."&employeetypesearch=".urlencode($EmployeeTypeSearch)."&majorcheck=".$MajorCheck."&majorsearch=".urlencode($MajorSearch)."&trackcheck=".$TrackCheck."&tracksearch=".urlencode($TrackSearch).
				"&judgecheck=".$JudgeCheck."&advisorcheck=".$AdvisorCheck."&missingcheck=".$MissingCheck."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".($Fields["UserStatus"] == ""?"Unknown":$StatusList[$Fields["UserStatus"]])."</A></TD>";
			echo "<TD>".$Fields["NetID"]."</TD>";
            echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>";
			echo "<TD>".$GenderList[$Fields["Gender"]]."</TD>";
            echo "<TD>".$Fields["OfficialEmail"]."</TD>";

            if ($Fields["DepartureDateUTC"] == 0)
                echo "<TD>&nbsp;</TD>";
            else if ($CurrentTime > $Fields["DepartureDateUTC"] + 126144000)
                echo "<TD CLASS=\"table-danger\">".date("m/d/Y", $Fields["DepartureDateUTC"])."</TD>";
            else if ($CurrentTime > $Fields["DepartureDateUTC"])
                echo "<TD CLASS=\"table-warning\">".date("m/d/Y", $Fields["DepartureDateUTC"])."</TD>";
            else
                echo "<TD>".date("m/d/Y", $Fields["DepartureDateUTC"])."</TD>";

            //Student type.
			echo "<TD>".$StudentTypeList[$Fields["StudentType"]]." ".$EmployeeTypeList[$Fields["EmployeeType"]]."</TD>";
			echo "<TD>".$Fields["Major"]." ".$TrackList[$Fields["Track"]]."</TD>";

            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." users found.";
    }
    else 
        echo "0 users found.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersCreateList.log"); 
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
