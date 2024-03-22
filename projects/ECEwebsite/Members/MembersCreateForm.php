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

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Access Control.
    //Staff only for this version of the page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit Member.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to create or edit a Member using this page.</DIV>";
		goto CloseSQL;
    }


    //Array of names for member status.
    $StatusList = array
      (
      "A"=>"Active",
      "D"=>"Deleted"
      );

    //Array of names for member type.
    $TypeList = array
      (
      "S"=>"Student",
      "A"=>"Advisor",
      "J"=>"Judge"
      );

    //Array of names for semester dropdown list box
    $SemesterList = array
      (
      "S"=>"Spring",
      "F"=>"Fall"
      );


	//Consolidated "Back to previous search" handling.
	$StatusCheck = "";
    if (isset($_GET["statuscheck"]))
	    if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
    if (isset($_GET["statussearch"]))
        if (!ValidateField("statussearch", "AD", 1))
			$StatusSearch = $_GET["statussearch"];

    $TypeCheck = "";
    if (isset($_GET["typecheck"]))
       	if (!ValidateField("typecheck", "checked", 7))
	        $TypeCheck = $_GET["typecheck"];

    $TypeSearch = "";
    if (isset($_GET["typesearch"]))
       	if (!ValidateField("typesearch", "SAJ", 1))
	        $TypeSearch = $_GET["typesearch"];

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

	$TeamSearch = "";
    if (isset($_GET["teamsearch"]))
       	if (!ValidateField("teamsearch", "0123456789", 3))
   		    $TeamSearch = $_GET["teamsearch"];

    $NetIDCheck = "";
    if (isset($_GET["netidcheck"]))
	    if (!ValidateField("netidcheck", "checked", 7))
		    $NetIDCheck = $_GET["netidcheck"];

    $NetIDSearch = "";
    if (isset($_GET["netidsearch"]))
       	if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyz0123456789", 20))
   		    $NetIDSearch = $_GET["netidsearch"];

    $Sort1 = "";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];

//echo "semestercheck=".$SemesterCheck." semestersearch=".$SemesterSearch."\r\n";

	//Populate the advisor dropdown with all the faculty members.
	//The advisor list is populated from a flag in the user table, instead of just faculty members, because some advisors are not faculty.
    $SQLQuery = "SELECT Users.UID, User.FirstName, User.LastName FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='CapstoneAdvisor' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS CapstoneAdvisor
            FROM Users) AS User
        ON User.UID=Users.UID
        WHERE User.CapstoneAdvisor='T' AND UserStatus<>'D' ORDER BY User.LastName;";
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


    //Initialize all the form data variables. These will be filled in later if an MemberID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.
	$Status = "";
	$Type = "";
	$TeamID = "";
	$UserID = "";
	$NetID = "";
	//$Semester = "";
	//$Year = "";
	$Team = "";


    $MemberID = 0;
    if (isset($_GET["mid"]))
       	if (!ValidateField("mid", "0123456789", 10))
	        $MemberID = $_GET["mid"];


	//Figure out the current semester and year.
	// From April 1 to November 1 is Fall semester
	$CurrentMonth = date('n', $CurrentTime);
    if ( ($CurrentMonth > 3) && ($CurrentMonth < 11) ) {
        $Semester = "F";
	    $Year = date('Y', $CurrentTime);
	}
    else {
	    $Semester = "S";
		if ($CurrentMonth < 4)
		    $Year = date('Y', $CurrentTime);
		else
		    $Year = date('Y', $CurrentTime) + 1;
	}


	//Some fields may need to be pre-populated if this isn't an existing member.
    if ($MemberID == 0) {

		if (isset($_GET["semester"]))
       		if (!ValidateField("semester", "SMF", 1))
   				$Semester = $_GET["semester"];

		if (isset($_GET["year"]))
       		if (!ValidateField("year", "0123456789", 4))
   				$Year = $_GET["year"];

		if (isset($_GET["team"]))
       		if (!ValidateField("team", "0123456789", 3))
   				$Team = $_GET["team"];

		if (isset($_GET["type"]))
       		if (!ValidateField("type", "SAJ", 1))
				$Type = $_GET["type"];
	}

    //If the MemberID is specified, get all the fields for this Member.
    if ($MemberID != 0) {

		$SQLQuery = "SELECT Members.MID, Members.CreateTime, Member.Status, Member.Type, Member.UserID, Member.TeamID, User.NetID, Team.Semester, Team.Year, Team.Number FROM Members
			LEFT JOIN
				(SELECT MID,
					(SELECT Value FROM MemberRecords WHERE Field='Status' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Status,
					(SELECT Value FROM MemberRecords WHERE Field='Type' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Type,
					(SELECT Value FROM MemberRecords WHERE Field='UserID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS UserID,
					(SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS TeamID
				FROM Members) AS Member
			ON Member.MID=Members.MID
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID
				FROM Users) AS User
			ON User.UID=Member.UserID
			LEFT JOIN
				(SELECT TID, Semester, Year, Number FROM Teams) AS Team
			ON Team.TID=Member.TeamID
			WHERE Members.MID=".$MemberID.";";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get Member: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Save the fields.
		$Fields = $SQLResult->fetch_assoc();
		$Status = $Fields["Status"];
		$Type = $Fields["Type"];
		$Semester = $Fields["Semester"];
		$Year = $Fields["Year"];
		$Team = $Fields["Number"];
		$NetID = $Fields["NetID"];
    }


    //Send back the Member page..
    echo "<FORM METHOD=\"POST\" ACTION=\"MembersProcessForm.php\">";

	//The create/update button.
    if ($MemberID != 0)
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Member\"> Back to <A HREF=\"MembersCreateList.php?statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&typecheck=".$TypeCheck."&typesearch=".urlencode($TypeSearch)."&semestercheck=".$SemesterCheck."&semestersearch=".urlencode($SemesterSearch)."&yearsearch=".urlencode($YearSearch)."&teamsearch=".urlencode($TeamSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A></P>";
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Member\"></P>";

    echo "<P>";
	echo "<STRONG>Status: </STRONG><BR>"; DropdownListBox("status", $StatusList, $Status); echo "<BR>\r\n";
    echo "<STRONG>Member Type:</STRONG><BR>"; DropdownListBox("type", $TypeList, $Type); echo "<BR>\r\n";
    echo "<STRONG>Semester:</STRONG><BR>"; DropdownListBox("semester", $SemesterList, $Semester); echo "<BR>\r\n";
    echo "<STRONG>Year:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"year\" VALUE=\"".$Year."\" SIZE=\"4\" MAXLENGTH=\"4\"><BR>\r\n";
    echo "<STRONG>Team Number:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"team\" VALUE=\"".$Team."\" SIZE=\"3\" MAXLENGTH=\"3\"><BR>\r\n";
    echo "<STRONG>NetID:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"netid\" VALUE=\"".$NetID."\" SIZE=\"10\" MAXLENGTH=\"20\"><BR>";
	echo "<STRONG>Or Advisor:</STRONG><BR>"; DropdownListBox("advisorid", $AdvisorNames, ""); echo "<BR>\r\n";
    echo "</P>";


	//Embed all the search fields so we can make a "return to search page" after the update is processed.
//    if ($MemberID != 0) {
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"      VALUE=\"".$StatusCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"     VALUE=\"".$StatusSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"typecheck\"        VALUE=\"".$TypeCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"typesearch\"       VALUE=\"".$TypeSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestercheck\"    VALUE=\"".$SemesterCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestersearch\"   VALUE=\"".$SemesterSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"yearsearch\"       VALUE=\"".$YearSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"teamsearch\"       VALUE=\"".$TeamSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidcheck\"       VALUE=\"".$NetIDCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidsearch\"      VALUE=\"".$NetIDSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"            VALUE=\"".$Sort1."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"            VALUE=\"".$Sort2."\">\r\n";
//	}

    //Notes are just another row in the change record table.
    if ($MemberID != 0) {

        //Get all the notes for this Member, sorted by timestamp.
        $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM MemberRecords AS T1 WHERE ID=".$MemberID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up Member notes ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing notes: ".$mysqli->error."</DIV>";
            goto CloseForm;
	    }

	    //Display the user query results in a table if any rows were found.
	    if ($SQLResult->num_rows > 0) {

		    echo "<P><STRONG>Notes and Change Log</STRONG><BR><TEXTAREA NAME=\"notes\" ROWS=\"10\" COLS=\"80\" readonly>";

            $ChangeUser = "";
            $ChangeTime = "";

		    while($Fields = $SQLResult->fetch_row() ) {

                //Spit out the change header only if this isn't the same user and timestamp.
                if ( ($Fields[1] != $ChangeUser) || ($Fields[4] != $ChangeTime) ) {
                    echo date("\r\nD F j, Y, g:i a", $Fields[1]).", User: ".$Fields[4]."\r\n";
                    $ChangeUser = $Fields[1];
                    $ChangeTime = $Fields[4];
                }

                //Output the change depending on the field - for notes just spit out the text - everything else it's " X changed to Y".
                if ($Fields[2] == "Notes")
			        echo "Additional note was added:\r\n".$Fields[3]."\r\n";
                else if ($Fields[2] == "Status")
                    echo " Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Type")
                    echo " Type changed to ".$TypeList[$Fields[3]]."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>New Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";


    //The create/update button.
    if ($MemberID != 0) {
        //If this is an existing Member store the MemberID in a hidden field.
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"mid\" VALUE=\"".$MemberID."\">\r\n";
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Member\"></P>";
    }
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Member\"></P>";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Members/MembersCreateForm.log"); 
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

