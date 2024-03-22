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
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member of faculty to create or edit an SPN request using this page.</DIV>";
		goto CloseSQL;
    }
 
    //Array of names for SPN request status dropdown list box
	//"W"=>"SOE Approved",
    $StatusList = array
      (
	  ""=>"",
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
	  ""=>"",
      "S"=>"Spring",
	  "M"=>"Summer",
      "F"=>"Fall"
      );

    //Array of names for type dropdown list box
    $TypeList = array
      (
	  ""=>"",
      "U"=>"Undergraduate",
	  "G"=>"Graduate",
	  "S"=>"Special Problems",
	  "I"=>"Internship"
      );

    //Initialize all the form data variables. These will be filled in later if an SPNID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.
    $Status = "R";
	$UserID = "";
	$Title = "";
	$Course = "";
	$Section = "";
	$FirstName = "";
	$LastName = "";
	$NetID = "";
	$Year = "";
	$Semester = "";

    $SPNID = 0;
	if (isset($_GET["spnid"]))
       	if (!ValidateField("spnid", "0123456789", 10))
		    $SPNID = $_GET["spnid"];

	//This is so the request type can be passed in and the correct version of the form created. Default is undergrad SPN.
	$Type = "U";
	if (isset($_GET["type"]))
       	if (!ValidateField("type", "UGSI", 1))
		    $Type = $_GET["type"];


	//Consolidated "Back to previous search" handling.
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
       	if (!ValidateField("typesearch", "UG", 1))
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
       	if (!ValidateField("sectionsearch", "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 2))
	        $SectionSearch = $_GET["sectionsearch"];

    $Sort1 = "";
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


    //If the SPNID is specified, get all the fields for this SPN request. With the change record table, the best approach at the moment
    // is to do a separate query for every field, sorted by timestamp so we just get the most recent field value.
    //If there is a query string, fetch SPN data from the table. SPN ID was already pre-initialized to 0.
    if ($SPNID != 0) {

		$Status = RetrieveField($SPNID, "Status");
		$Type = RetrieveField($SPNID, "Type");
    	$UserID = RetrieveField($SPNID, "UserID");
		$Title = RetrieveField($SPNID, "Title");
		$Course = RetrieveField($SPNID, "Course");
    	$Section = RetrieveField($SPNID, "Section");
    	$Year = RetrieveField($SPNID, "Year");
    	$Semester = RetrieveField($SPNID, "Semester");

        //We need the name and NetID of the user that created this SPN request.
        if ($UserID != "") {
		    $SQLQuery = "SELECT
			    UID,
			    (SELECT Value FROM UserRecords WHERE ID='".$UserID."' AND Field='NetID' ORDER BY CreateTime DESC LIMIT 1) AS NetID
			    FROM Users WHERE UID='".$UserID."';";
	        $SQLResult = $mysqli->query($SQLQuery);
	        if ($mysqli->error) {
		        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		    echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve NetID of SPN requester: ".$mysqli->error."</DIV>";
		        goto CloseSQL;
	        }

            //Make sure only one user was returned.
	        if ($SQLResult->num_rows != 1) {
		        WriteLog("User ID ".$UserID." not found in Users table.");
    		    echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">The SPN requester could not be found.</DIV>";
		        goto CloseSQL;
            }

            //Save the fields.
            $Fields = $SQLResult->fetch_row();
		    $NetID = $Fields[1];
        }
    }


    //Send back the SPN request page..
    echo "<FORM METHOD=\"POST\" ACTION=\"SPNReqStaffProcessForm.php\" ENCTYPE=\"multipart/form-data\">";

	//The create/update button.
    if ($SPNID != 0)
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Request\"> Back to <A HREF=\"SPNReqStaffCreateList.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&typecheck=".$TypeCheck."&typesearch=".$TypeSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionsearch=".urlencode($SectionSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."&sort3=".urlencode($Sort3)."\">previous search</A></P>";
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Request\"></P>";

    echo "<P>";
	echo "<STRONG>Status: </STRONG><BR>"; DropdownListBox("status", $StatusList, $Status); echo "<BR>\r\n";
	echo "<STRONG>Type: </STRONG><BR>"; DropdownListBox("type", $TypeList, $Type); echo "<BR>\r\n";
    echo "<STRONG>NetID:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"netid\" VALUE=\"".$NetID."\" SIZE=\"10\" MAXLENGTH=\"25\"><BR>\r\n";
    echo "<STRONG>Semester:</STRONG><BR>"; DropdownListBox("semester", $SemesterList, $Semester); echo "<BR>\r\n";
    echo "<STRONG>Year:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"year\" VALUE=\"".$Year."\" SIZE=\"4\" MAXLENGTH=\"4\"><BR>\r\n";
    echo "<STRONG>Course Title:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"title\" VALUE=\"".$Title."\" SIZE=\"50\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Course Number:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"course\" VALUE=\"".$Course."\" SIZE=\"3\" MAXLENGTH=\"3\"><BR>\r\n";
    echo "<STRONG>Section Number:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"section\" VALUE=\"".$Section."\" SIZE=\"2\"  MAXLENGTH=\"2\"><BR>\r\n";

	//If this is a special problems SPN, there has to be a project description.
	if ($Type == "S") {

		if ($SPNID != 0) {
			//Check if a project description file already exists and put a link so it can be downloaded.
	        if (file_exists("ProjectDescriptions/Description".$SPNID.".pdf")) {
				echo "<A HREF=\"https://apps.ece.rutgers.edu/SPN/ProjectDescriptions/Description".$SPNID.".pdf\">Description".$SPNID.".pdf</A>\r\n";
			}
		}
		else {
			echo "<STRONG>Project Description:</STRONG><BR>";
			echo "<INPUT TYPE=\"file\" NAME=\"projectdesc\">\r\n";
		}
	}

    echo "</P>";

	//Embed all the search fields so we can make a "return to search page" after the update is processed.
//    if ($SPNID != 0) {
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"      VALUE=\"".$StatusCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"     VALUE=\"".$StatusSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"typecheck\"        VALUE=\"".$TypeCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"typesearch\"       VALUE=\"".$TypeSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestercheck\"    VALUE=\"".$SemesterCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"semestersearch\"   VALUE=\"".$SemesterSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"yearsearch\"       VALUE=\"".$YearSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidcheck\"       VALUE=\"".$NetIDCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidsearch\"      VALUE=\"".$NetIDSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"coursecheck\"      VALUE=\"".$CourseCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"coursesearch\"     VALUE=\"".$CourseSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sectioncheck\"     VALUE=\"".$SectionCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sectionsearch\"    VALUE=\"".$SectionSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"            VALUE=\"".$Sort1."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"            VALUE=\"".$Sort2."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort3\"            VALUE=\"".$Sort3."\">\r\n";
//	}

    //Notes are just another row in the change record table.
    if ($SPNID != 0) {

        //Get all the notes for this SPN, sorted by timestamp.
        $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM SPNRequestRecords AS T1 WHERE ID=".$SPNID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up SPN request notes ".$SQLQuery);
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
                    echo " Request Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Type")
                    echo " Type changed to ".$TypeList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Semester")
                    echo " Semester changed to ".$SemesterList[$Fields[3]]."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>Additional Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";


    //The create/update button.
    if ($SPNID != 0) {
        //If this is an existing SPN store the SPN ID in a hidden field.
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"spnid\" VALUE=\"".$SPNID."\">\r\n";
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Request\"></P>";
    }
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Request\"></P>";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNReqStaffCreateForm.log"); 
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
    function RetrieveField($SID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM SPNRequestRecords WHERE ID=".$SID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
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

