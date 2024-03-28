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
	//$UserDoingUpdateNetID = "db1359";

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
    if ($UserDoingUpdateAccessRole != "F") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit a user.");
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

    $SortList = array
      (
      ""=>"",
      "LastName"=>"Last Name",
      "OfficialEmail"=>"Email Address",
      "NetID"=>"NetID",
      "StudentType"=>"Student Type",
      "UserStatus"=>"User Status",
      "DepartureDateUTC"=>"Graduation Date"
      );


	//Populate the advisor dropdown with all the faculty members.
	//Can PTL and NTT faculty be advisors??
    $SQLQuery = "SELECT Users.UID, User.FirstName, User.LastName FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='EmployeeType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS EmployeeType,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='Major' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Major
            FROM Users) AS User
        ON User.UID=Users.UID
        WHERE UserStatus='A' AND LOCATE(EmployeeType, 'FAN') AND Major='332' ORDER BY LastName;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct advisor list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Add each faculty member to the associative array.
    $AdvisorNames[""] = "";
    while($Fields = $SQLResult->fetch_row() )
        $AdvisorNames[$Fields[0]] = $Fields[2].", ".$Fields[1];


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

    $NameCheck = "";
    if (isset($_GET["namecheck"]))
       	if (!ValidateField("namecheck", "checked", 7))
		    $NameCheck = $_GET["namecheck"];

    $NameSearch = "";
    if (isset($_GET["namesearch"]))
	    if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 50))
            $NameSearch = $_GET["namesearch"];

    $StudentTypeCheck = "";
    if (isset($_GET["studenttypecheck"]))
       	if (!ValidateField("studenttypecheck", "checked", 7))
		    $StudentTypeCheck = $_GET["studenttypecheck"];

    $StudentTypeSearch = "";
    if (isset($_GET["studenttypesearch"]))
	    if (!ValidateField("studenttypesearch", "UMWPN", 10))
			$StudentTypeSearch = $_GET["studenttypesearch"];

    $AdvisorCheck = "";
    if (isset($_GET["advisorcheck"]))
       	if (!ValidateField("advisorcheck", "checked", 7))
		    $AdvisorCheck = $_GET["advisorcheck"];

    $AdvisorSearch = $UserDoingUpdateUID;
    if (isset($_GET["advisorsearch"]))
	    if (!ValidateField("advisorsearch", "0123456789", 10))
			$AdvisorSearch = $_GET["advisorsearch"];

    $Sort1 = "LastName";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"UsersFacultyCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\" ".$StatusCheck."> Show only "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo " students.</TD>\r\n";
    echo "<TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TD>";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\" ".$NetIDCheck."> Show only students with NetID <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"10\"></TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"namecheck\" ".$NameCheck."> Show only students with first or last name <INPUT TYPE=\"text\" NAME=\"namesearch\" VALUE=\"".$NameSearch."\" SIZE=\"15\" MAXLENGTH=\"30\"></TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"studenttypecheck\" ".$StudentTypeCheck."> Show only "; DropdownListBox("studenttypesearch", $StudentTypeList, $StudentTypeSearch); echo " students.</TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"advisorcheck\" ".$AdvisorCheck."> Show only students advised by "; DropdownListBox("advisorsearch", $AdvisorNames, $AdvisorSearch); echo "</TD>\r\n";
    echo "</TR>";
    echo "<TR>";
    echo "<TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD>\r\n";
    echo "</TR>";
    echo "</TBODY></TABLE>";

	//If we are searching for a particular user, and it's the zero user, then find nothing.
	if ($UserIDSearch == "0") {
		echo "0 students found.";
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
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (UserStatus='".mysqli_real_escape_string($mysqli, $StatusSearch)."') ";
	//LOCATE() has issues with blank or null strings.
    if ($StudentTypeCheck && $StudentTypeSearch)
	    $Conditions .= "AND (StudentType<>'') AND LOCATE(StudentType,'".mysqli_real_escape_string($mysqli, $StudentTypeSearch)."') ";
    if ($AdvisorCheck)
	    $Conditions .= "AND (Advisor1UID='".$AdvisorSearch."') ";
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
    $SQLQuery = "SELECT Users.UID, User.NetID, User.UserStatus, User.FirstName, User.LastName, User.PreferredEmail, User.DepartureDateUTC, User.StudentType, User.Major FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='PreferredEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PreferredEmail,
                (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC,
                (SELECT Value FROM UserRecords WHERE Field='StudentType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS StudentType,
                (SELECT Value FROM UserRecords WHERE Field='Major' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Major,
                (SELECT Value FROM UserRecords WHERE Field='Advisor1UID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Advisor1UID
            FROM Users) AS User
        ON Users.UID=User.UID
        ".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get students list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." students found.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>NetID</TH><TH>Full Name</TH><TH>Email</TH><TH>Departure</TH><TH>Type</TH></TH></TR>\r\n";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            echo "<TR>";
			echo "<TD><A HREF=\"UsersFacultyCreateForm.php?userid=".$Fields["UID"]."&statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&studenttypecheck=".$StudentTypeCheck."&studenttypesearch=".urlencode($StudentTypeSearch)."&advisorcheck=".$AdvisorCheck."&advisorsearch=".$AdvisorSearch."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".($Fields["UserStatus"] == ""?"Unknown":$StatusList[$Fields["UserStatus"]])."</A></TD>";
			echo "<TD>".$Fields["NetID"]."</TD>";
            echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>";
            echo "<TD>".$Fields["PreferredEmail"]."</TD>";

            if ($Fields["DepartureDateUTC"] == 0)
                echo "<TD>&nbsp;</TD>";
            else if ($CurrentTime > $Fields["DepartureDateUTC"] + 126144000)
                echo "<TD CLASS=\"table-danger\">".date("m/d/Y", $Fields["DepartureDateUTC"])."</TD>";
            else if ($CurrentTime > $Fields["DepartureDateUTC"])
                echo "<TD CLASS=\"table-warning\">".date("m/d/Y", $Fields["DepartureDateUTC"])."</TD>";
            else
                echo "<TD>".date("m/d/Y", $Fields["DepartureDateUTC"])."</TD>";

            //Student type.
			echo "<TD>".$StudentTypeList[$Fields["StudentType"]]."</TD>";
            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." students found.";
    }
    else 
        echo "0 students found.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersFacultyCreateList.log"); 
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
