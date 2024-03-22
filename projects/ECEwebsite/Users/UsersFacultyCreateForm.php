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

	//Assume the user is updating their own information, unless there is a userid field.
	//This has to be before the menus go out, because $UserBeingUpdatedUID is needed by the menu code.
    $UserBeingUpdatedUID = $UserDoingUpdateUID;
    if (isset($_GET["userid"]))
       	if (!ValidateField("userid", "0123456789 ", 10))
		    $UserBeingUpdatedUID = $_GET["userid"];


	//This has to go out after the user is authenticated and their role is determined, because the menus are based on role.
	require('../template/head.php');

    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Access checks.
    if ($UserDoingUpdateAccessRole != "F") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit userID ".$UserBeingUpdatedUID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have permission to edit this user.</DIV>";
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

    $AccessRoleList = array
      (
      ""=>"",
      "D"=>"ECE Student",
      "S"=>"ECE Staff",
      "F"=>"ECE Faculty",
      "A"=>"SOE Approver",
      "O"=>"External"
      );

    $StudentTypeList = array
      (
      ""=>"",
      "U"=>"Undergraduate",
      "M"=>"MS With Thesis",
      "W"=>"MS Without Thesis",
      "P"=>"PhD",
      "N"=>"Non-Matriculated"
      );

    $EmployeeTypeList = array
      (
      ""=>"",
      "S"=>"Staff",
      "F"=>"Faculty",
	  "A"=>"Adjunct",
	  "N"=>"Non-Tenure",
      "T"=>"TA",
      "G"=>"GA",
      "W"=>"Work/Study",
      "P"=>"Postdoc",
      "E"=>"Fellow",
      "R"=>"Research",
      "H"=>"Hourly",
      "Z"=>"Unknown"
      );

    $TrackList = array
      (
      ""=>"",
      "A"=>"Computer Engineering",
      "B"=>"Electrical Engineering"
      );

    $CitizenList = array
      (
      ""=>"",
      "U"=>"US Citizen",
      "F"=>"Foreign Citizen",
      "P"=>"Permanent Resident",
      "D"=>"DACA",
      "Z"=>"Unknown"
      );

    $VisaList = array
      (
      ""=>"",
      "F1"=>"F1",
      "J1"=>"J1",
      "J3"=>"J3",
      "H1"=>"H1",
      "H2"=>"H2",
      "H4"=>"H4",
      "AP"=>"AP (Advance Parole)",
      "FX"=>"FX (Spouse of permanent resident)",
      "UN"=>"Unknown"
      );

    $GenderList = array
      (
      ""=>"",
      "M"=>"Male",
      "F"=>"Female",
      "Z"=>"Other"
      );


    //Initialize all the form data variables. These will be filled in later if a UserID is present in the query string.
    $UserStatus = "";
    $NetID = "";
    $RUID = "";
    $Prefix = "";
	$FirstName = "";
	$MiddleName = "";
	$LastName = "";
    $Suffix = "";
    $Gender = "";
    $OfficialEmail = "";
    $PreferredEmail = "";
    $AlternateEmail = "";
    $Phone1 = "";
    $Phone2 = "";
    $Street1 = "";
    $Street2 = "";
    $City = "";
    $State = "";
    $Zip = "";
    $Country = "";
    $StudentType = "";
    $DepartureDate = "";
    $Advisor1 = "";
    $Advisor2 = "";
//    $NJResident = "";
    $Credits = "";
//    $Citizen = "";
//    $Visa = "";

	//Consolidated "Back to previous search" handling.
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

    $AdvisorSearch = "";
    if (isset($_GET["advisorsearch"]))
	    if (!ValidateField("advisorsearch", "0123456789", 10))
			$AdvisorSearch = $_GET["advisorsearch"];

    $Sort1 = "";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort2 = $_GET["sort2"];



    //If the UserID is specified, get all the fields for this user. With the change record table, the best approach at the moment
    // is to do a separate query for every field, sorted by timestamp so we just get the most recent field value.
    if ($UserBeingUpdatedUID != 0) {	

    	$UserStatus = RetrieveField($UserBeingUpdatedUID, "UserStatus");
        $NetID = RetrieveField($UserBeingUpdatedUID, "NetID");
    	$RUID = RetrieveField($UserBeingUpdatedUID, "RUID");
        $Prefix = RetrieveField($UserBeingUpdatedUID, "Prefix");
	    $FirstName = RetrieveField($UserBeingUpdatedUID, "FirstName");
	    $MiddleName = RetrieveField($UserBeingUpdatedUID, "MiddleName");
	    $LastName = RetrieveField($UserBeingUpdatedUID, "LastName");
        $Suffix = RetrieveField($UserBeingUpdatedUID, "Suffix");
        $Gender = RetrieveField($UserBeingUpdatedUID, "Gender");
        $OfficialEmail = RetrieveField($UserBeingUpdatedUID, "OfficialEmail");
        $PreferredEmail = RetrieveField($UserBeingUpdatedUID, "PreferredEmail");
        $AlternateEmail = RetrieveField($UserBeingUpdatedUID, "AlternateEmail");
        $Phone1 = RetrieveField($UserBeingUpdatedUID, "Phone1");
        $Phone2 = RetrieveField($UserBeingUpdatedUID, "Phone2");
        $Street1 = RetrieveField($UserBeingUpdatedUID, "Street1");
        $Street2 = RetrieveField($UserBeingUpdatedUID, "Street2");
        $City = RetrieveField($UserBeingUpdatedUID, "City");
        $State = RetrieveField($UserBeingUpdatedUID, "State");
        $Zip = RetrieveField($UserBeingUpdatedUID, "Zip");
        $Country = RetrieveField($UserBeingUpdatedUID, "Country");

        $StudentType = RetrieveField($UserBeingUpdatedUID, "StudentType");
        if ( ($DepartureDateUTC = RetrieveField($UserBeingUpdatedUID, "DepartureDateUTC")) != "")
			$DepartureDate = date("m/d/Y", (int)$DepartureDateUTC);

        $Advisor1 = RetrieveField($UserBeingUpdatedUID, "Advisor1UID");
        $Advisor2 = RetrieveField($UserBeingUpdatedUID, "Advisor2UID");
//        $NJResident = RetrieveField($UserBeingUpdatedUID, "NJResident");
//	    if ($NJResident == "T")
//		    $NJResident = "CHECKED";
//        $Citizen = RetrieveField($UserBeingUpdatedUID, "Citizen");
//        $Visa = RetrieveField($UserBeingUpdatedUID, "Visa");
        $Credits = RetrieveField($UserBeingUpdatedUID, "Credits");

		//Get the Capstone Team information for this student.
		//The user ID of team members are stored in "StudentX" fields of the TeamRecords table.
		//We need to find the student based on their ID in the TeamRecords to get TeamID, and then get team data from Teams table.
		$SQLQuery = "SELECT Semester, Year, Number FROM Teams WHERE TID=(SELECT ID FROM TeamRecords WHERE Field LIKE 'Student%' AND Value=".$UserBeingUpdatedUID." ORDER BY CreateTime DESC LIMIT 1);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve Capstone team information: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Grab the fields if something was found - otherwise, don't worry about it (user is not assigned to a capstone team).
		if ($SQLResult->num_rows != 0) {
			//Student is assigned to a team, and the team information was found - save it.
			$Fields = $SQLResult->fetch_row();
			$TeamSeason = $Fields[0];
			$TeamYear = $Fields[1];
			$TeamNumber = $Fields[2];
		}
    }

	//Populate the advisor dropdown with all the faculty members.
    $SQLQuery = "SELECT Users.UID, Faculty.FirstName, Faculty.LastName FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='EmployeeType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS EmployeeType
            FROM Users) AS Faculty
        ON Users.UID=Faculty.UID
        WHERE LOCATE(EmployeeType,'FAN') AND UserStatus='A' ORDER BY LastName;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct advisor list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Add each faculty member to the associative array.
    $AdvisorList[""] = "";
    while($Fields = $SQLResult->fetch_row() )
        $AdvisorList[$Fields[0]] = $Fields[2].", ".$Fields[1];


    echo "<FORM METHOD=\"POST\" ACTION=\"UsersFacultyProcessForm.php\">";
    //echo "<img src=\"../Photos/".$UserBeingUpdatedUID.".jpg\" class=\"rounded float-right\" width=\"240\" height=\"320\">";

    //The create/update button.
    if ($UserBeingUpdatedUID != 0) {
		echo "<P>";
	    echo "<INPUT TYPE=\"SUBMIT\" VALUE=\"Update User\"> Back to <A HREF=\"UsersFacultyCreateList.php?statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&studenttypecheck=".$StudentTypeCheck."&studenttypesearch=".urlencode($StudentTypeSearch)."&advisorcheck=".$AdvisorCheck."&advisorsearch=".$AdvisorSearch."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.\r\n";
		echo " Create a new <A HREF=\"../Appts/ApptsFacultyCreateForm.php?studentid=".$UserBeingUpdatedUID."\">TA/GA Appointment Request</A>.\r\n";
		echo "</P>";
	}
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create User\"></P>";

//    if ( ($StudentType == "M") || ($StudentType == "W") || ($StudentType == "P") )
//        echo "<P>Create TA/GA/Fellowship <A HREF=\"https://apps.ece.rutgers.edu/Appt/ApptsCreateNew.php?studentid=".$UserBeingUpdatedUID."\">appointment</A> for this student.</P>";

    echo "<P>";
    echo "<STRONG>User Status</STRONG><BR>"; DropdownListBox("userstatus", $StatusList, $UserStatus); echo "<BR>\r\n";
    echo "<STRONG>NetID</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"netid\" VALUE=\"".$NetID."\" SIZE=\"5\"  MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>RUID</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"ruid\"  VALUE=\"".$RUID."\"  SIZE=\"10\"  MAXLENGTH=\"10\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Prefix</STRONG>     <BR>\r\n<INPUT TYPE=\"text\" NAME=\"prefix\"     VALUE=\"".$Prefix."\"     SIZE=\"5\"  MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>First name</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"firstname\"  VALUE=\"".$FirstName."\"  SIZE=\"30\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Middle name</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"middlename\" VALUE=\"".$MiddleName."\" SIZE=\"30\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Last name</STRONG>  <BR>\r\n<INPUT TYPE=\"text\" NAME=\"lastname\"   VALUE=\"".$LastName."\"   SIZE=\"30\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Suffix</STRONG>     <BR>\r\n<INPUT TYPE=\"text\" NAME=\"suffix\"     VALUE=\"".$Suffix."\"     SIZE=\"5\"  MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Gender</STRONG><BR>"; DropdownListBox("gender", $GenderList, $Gender); echo "<BR>\r\n";
    echo "</P>";
    echo "<P>";
    echo "<STRONG>Official Rutgers Email </STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"officialemail\"   VALUE=\"".$OfficialEmail. "\"  SIZE=\"40\"  MAXLENGTH=\"80\"><BR>\r\n";
    echo "<STRONG>Alternate/Personal Email</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"alternateemail\"  VALUE=\"".$AlternateEmail."\"  SIZE=\"40\"  MAXLENGTH=\"80\"><BR>\r\n";
    echo "<STRONG>Preferred Email</STRONG> (this is the address used by the automated emailer)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"preferredemail\"  VALUE=\"".$PreferredEmail."\"  SIZE=\"40\"  MAXLENGTH=\"80\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Phone 1</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"phone1\" VALUE=\"".$Phone1."\"SIZE=\"15\"  MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Phone 2</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"phone2\" VALUE=\"".$Phone2."\"SIZE=\"15\"  MAXLENGTH=\"50\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Street 1</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"street1\" VALUE=\"".$Street1."\"SIZE=\"30\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Street 2</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"street2\" VALUE=\"".$Street2."\"SIZE=\"30\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>City</STRONG>     <BR>\r\n<INPUT TYPE=\"text\" NAME=\"city\"    VALUE=\"".$City."\"   SIZE=\"20\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>State</STRONG>    <BR>\r\n<INPUT TYPE=\"text\" NAME=\"state\"   VALUE=\"".$State."\"  SIZE=\"10\" MAXLENGTH=\"30\"><BR>\r\n";
    echo "<STRONG>ZIP</STRONG>      <BR>\r\n<INPUT TYPE=\"text\" NAME=\"zip\"     VALUE=\"".$Zip."\"    SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Country</STRONG>  <BR>\r\n<INPUT TYPE=\"text\" NAME=\"country\" VALUE=\"".$Country."\"SIZE=\"15\" MAXLENGTH=\"30\"><BR>\r\n";
    echo "</P>";

    echo "<HR>";
    echo "<H4>Student Information</H3>";

    echo "<P>";
    echo "<STRONG>Student Type</STRONG><BR>"; DropdownListBox("studenttype", $StudentTypeList, $StudentType); echo "<BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Expected Graduation or Departure Date (Month/Day/Year)</STRONG><BR>\r\n";
    echo "<INPUT TYPE=\"text\" NAME=\"departdate\" VALUE=\"".$DepartureDate."\" SIZE=\"10\"  MAXLENGTH=\"10\">\r\n";
    echo "</P>";

    echo "<HR>";
    echo "<H4>Graduate Student Information</H3>";

    echo "<P>";
    echo "<STRONG>Advisor 1</STRONG><BR>"; DropdownListBox("advisor1uid", $AdvisorList, $Advisor1); echo "<BR>\r\n";
    echo "<STRONG>Advisor 2</STRONG><BR>"; DropdownListBox("advisor2uid", $AdvisorList, $Advisor2); echo "<BR>\r\n";
//    echo "<STRONG>Citizen Status</STRONG><BR>"; DropdownListBox("citizen", $CitizenList, $Citizen); echo "<BR>\r\n";
//    echo "<STRONG>Visa Status</STRONG><BR>"; DropdownListBox("visa", $VisaList, $Visa); echo "<BR>\r\n";
//    echo "<INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"njresident\" ".$NJResident.">In-state resident<BR>\r\n";
    echo "<STRONG>Credits</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"credits\" VALUE=\"".$Credits."\" SIZE=\"5\" MAXLENGTH=\"5\"><BR>\r\n";
    echo "</P>";

    echo "<HR>";

    //If this is an existing user store the ID in a hidden field.
	//Embed all the search fields so we can make a "return to search page" after the update is processed.
    if ($UserBeingUpdatedUID != 0) {
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"       VALUE=\"".$StatusCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"      VALUE=\"".$StatusSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidcheck\"        VALUE=\"".$NetIDCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidsearch\"       VALUE=\"".$NetIDSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"namecheck\"         VALUE=\"".$NameCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"namesearch\"        VALUE=\"".$NameSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"studenttypecheck\"  VALUE=\"".$StudentTypeCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"studenttypesearch\" VALUE=\"".$StudentTypeSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"             VALUE=\"".$Sort1."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"             VALUE=\"".$Sort2."\">\r\n";
	}


    //Notes are just another row in the change record table.
    if ($UserBeingUpdatedUID != 0) {

        //Get all the notes for this user, sorted by timestamp.
        $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM UserRecords AS T1 WHERE ID=".$UserBeingUpdatedUID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up user notes ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing notes: ".$mysqli->error."</DIV>";
            goto CloseForm;
	    }

	    //Output all the change records in a text box.
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
                else if ($Fields[2] == "UserStatus")
                    echo " User Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "EmployeeType")
                    echo " Employee Type changed to ".$EmployeeTypeList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "StudentType")
                    echo " Student Type changed to ".$StudentTypeList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "AccessRole")
                    echo " Access Role changed to ".$AccessRoleList[$Fields[3]]."\r\n";
                else if ( ($Fields[2] == "DepartureDateUTC") && ($Fields[3] != "") )
                    echo " Departure Date changed to ".date("F j, Y, g:i a", $Fields[3])."\r\n";
                else if ( ($Fields[2] == "SafetyTrainingUTC") && ($Fields[3] != "") )
                    echo " Safety Training Date changed to ".date("F j, Y, g:i a", $Fields[3])."\r\n";
                else if ( ($Fields[2] == "HandsOnTrainingUTC") && ($Fields[3] != "") )
                    echo " Hands-On Training Date changed to ".date("F j, Y, g:i a", $Fields[3])."\r\n";
                else if ( ($Fields[2] == "StartDateUTC") && ($Fields[3] != "") )
                    echo " Start Date changed to ".date("F j, Y, g:i a", $Fields[3])."\r\n";
                else if ($Fields[2] == "Track")
                    echo " Track changed to ".$TrackList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Gender")
                    echo " Gender changed to ".$GenderList[$Fields[3]]."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>Additional Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"10\" COLS=\"80\"></TEXTAREA></P>";

    //If this is an existing user store the ID in a hidden field.
    if ($UserBeingUpdatedUID != 0)
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"userid\" VALUE=\"".$UserBeingUpdatedUID."\">\r\n";

    //The create/update button.
    if ($UserBeingUpdatedUID != 0)
	    printf("<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update User\"></P>");
    else
	    printf("<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create User\"></P>");

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
    //Function to write a string to the log. Getting permission denied... SE Linux issue?
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersFacultyCreateForm.log"); 
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


    //Function to retrieve a specified field for a specified user.
    function RetrieveField($UID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM UserRecords WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve user field from database: ".$mysqli->error."</DIV>";
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

