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
	$Menu = "SPNs";
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
    //Staff only for this version of the page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to create or edit an SPN request using this page.</DIV>";
		goto CloseSQL;
    }
 
    //Array of names for SPN request status dropdown list box
    $StatusList = array
      (
      "A"=>"Available",
      "U"=>"Used",
      "I"=>"Invalid"
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


    //Initialize all the form data variables. These will be filled in later if an SPNID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.
	$Status = "";
	$Type = "";
	$Semester = "";
	$Year = "";
	$Course = "";
	$Section = "";
	$SPN = "";
	$NetID = "";

    $SPNID = 0;
    if (isset($_GET["spnid"]))
       	if (!ValidateField("spnid", "0123456789", 10))
	        $SPNID = $_GET["spnid"];


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


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
	        $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
    if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "AUI", 5))
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

    $SemesterSearch = "";
    if (isset($_GET["semestersearch"]))
       	if (!ValidateField("semestersearch", "SMF", 1))
	        $SemesterSearch = $_GET["semestersearch"];

    $YearSearch = "";
    if (isset($_GET["yearsearch"]))
       	if (!ValidateField("yearsearch", "0123456789", 4))
	        $YearSearch = $_GET["yearsearch"];

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
       	if (!ValidateField("sectionsearch", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 2))
	        $SectionSearch = $_GET["sectionsearch"];

    $SPNCheck = "";
    if (isset($_GET["spncheck"]))
       	if (!ValidateField("spncheck", "checked", 7))
	        $SPNCheck = $_GET["spncheck"];

    $SPNSearch = "";
    if (isset($_GET["spnsearch"]))
       	if (!ValidateField("spnsearch", "0123456789", 6))
	        $SPNSearch = $_GET["spnsearch"];

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


    //If the SPNID is specified, get all the fields for this SPN request. With the change record table, the best approach at the moment
    // is to do a separate query for every field, sorted by timestamp so we just get the most recent field value.
    //If there is a query string, fetch SPN data from the table. SPN ID was already pre-initialized to 0.
    if ($SPNID != 0) {

		$SQLQuery = "SELECT SPNs.SID, SPNs.CreateTime, SPNs.Type, SPNs.Semester, SPNs.Year, SPNs.Course, SPNs.Section, SPNs.SPN, SPN.Status, SPN.UserID, User.NetID FROM SPNs
			LEFT JOIN
				(SELECT SID,
					(SELECT Value FROM SPNRecords WHERE Field='Status' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS Status,
					(SELECT Value FROM SPNRecords WHERE Field='UserID' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS UserID
				FROM SPNs) AS SPN
			ON SPNs.SID=SPN.SID
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID
				FROM Users) AS User
			ON User.UID=SPN.UserID
			WHERE SPNs.SID=".$SPNID.";";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get SPN list: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Save the fields.
		$Fields = $SQLResult->fetch_assoc();
		$Status = $Fields["Status"];
		$Type = $Fields["Type"];
		$Semester = $Fields["Semester"];
		$Year = $Fields["Year"];
		$Course = $Fields["Course"];
		$Section = $Fields["Section"];
		$SPN = $Fields["SPN"];
		$NetID = $Fields["NetID"];
    }


    //Send back the SPN request page..
    echo "<FORM METHOD=\"POST\" ACTION=\"SPNProcessForm.php\">";

	//The create/update button.
    if ($SPNID != 0)
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update SPN\"> Back to <A HREF=\"SPNCreateList.php?statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionsearch=".urlencode($SectionSearch)."&spncheck=".$SPNCheck."&spnsearch=".urlencode($SPNSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A></P>";
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create SPN\"></P>";

    echo "<P>";
	echo "<STRONG>Status: </STRONG><BR>"; DropdownListBox("status", $StatusList, $Status); echo "<BR>\r\n";
    echo "<STRONG>Type:</STRONG><BR>"; DropdownListBox("type", $TypeList, $Type); echo "<BR>\r\n";
    echo "<STRONG>Semester:</STRONG><BR>"; DropdownListBox("semester", $SemesterList, $Semester); echo "<BR>\r\n";
    echo "<STRONG>Year:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"year\" VALUE=\"".$Year."\" SIZE=\"4\" MAXLENGTH=\"4\"><BR>\r\n";
    echo "<STRONG>Course Number:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"course\" VALUE=\"".$Course."\" SIZE=\"3\" MAXLENGTH=\"3\"><BR>\r\n";
    echo "<STRONG>Section Number:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"section\" VALUE=\"".$Section."\" SIZE=\"2\"  MAXLENGTH=\"2\"><BR>\r\n";
    echo "<STRONG>SPN:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"spn\" VALUE=\"".$SPN."\" SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Assigned to NetID:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"netid\" VALUE=\"".$NetID."\" SIZE=\"10\" MAXLENGTH=\"20\"><BR>\r\n";
    echo "</P>";


	//Embed all the search fields so we can make a "return to search page" after the update is processed.
    if ($SPNID != 0) {
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"      VALUE=\"".$StatusCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"     VALUE=\"".$StatusSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestercheck\"    VALUE=\"".$SemesterCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestersearch\"   VALUE=\"".$SemesterSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"yearsearch\"       VALUE=\"".$YearSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"coursecheck\"      VALUE=\"".$CourseCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"coursesearch\"     VALUE=\"".$CourseSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sectioncheck\"     VALUE=\"".$SectionCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sectionsearch\"    VALUE=\"".$SectionSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"spncheck\"         VALUE=\"".$SPNCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"spnsearch\"        VALUE=\"".$SPNSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidcheck\"       VALUE=\"".$NetIDCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidsearch\"      VALUE=\"".$NetIDSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"            VALUE=\"".$Sort1."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"            VALUE=\"".$Sort2."\">\r\n";
	}

    //Notes are just another row in the change record table.
    if ($SPNID != 0) {

        //Get all the notes for this SPN, sorted by timestamp.
        $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM SPNRecords AS T1 WHERE ID=".$SPNID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up SPN notes ".$SQLQuery);
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
                    echo " SPN Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>New Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";


    //The create/update button.
    if ($SPNID != 0) {
        //If this is an existing SPN store the SPN ID in a hidden field.
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"spnid\" VALUE=\"".$SPNID."\">\r\n";
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update SPN\"></P>";
    }
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create SPN\"></P>";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNCreateForm.log"); 
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

