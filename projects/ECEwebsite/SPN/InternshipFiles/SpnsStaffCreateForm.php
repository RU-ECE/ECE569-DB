<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

    //Set for the title of the page.
    $title = "ECE Apps - Staff Portal";
    $Menu = "Teams";
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
    $UserNetID = $_SESSION['netid'];

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
    $SQLQuery = "SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$UserNetID."' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    	require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for user ".$UserNetID." has failed: ".$mysqli->error."</DIV>";
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows < 1) {
   		WriteLog("User with NetID ".$UserNetID." not found.");
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User ".$UserNetID." not found.</DIV>";
		goto CloseSQL;
	}

    //Extract the User ID and their access role.
	$Fields = $SQLResult->fetch_row();
    $UserID = $Fields[0];
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


    //Access checks.
    //Only staff is allowed to view/change all teams.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserID." with access role ".$UserDoingUpdateAccessRole." is trying to edit TeamID ".$VendorID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have permission to edit this team.</DIV>";
		goto CloseSQL;
    }

 
    //Array of names for user status dropdown list box
    $StatusList = array
      (
      "F"=>"Forming",
      "P"=>"Uploading Proposal",
      "B"=>"Building",
      "O"=>"Uploading Poster",
      "V"=>"Uploading Video",
      "C"=>"Completed"
      );

    //Array of names for team season dropdown list box
    $TeamSeasonList = array
      (
      "S"=>"S",
      "F"=>"F"
      );


	//Populate the advisor dropdown with all the faculty members.
    $SQLQuery = "SELECT Users.UID, People.FirstName, People.LastName FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='EmployeeType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS EmployeeType
            FROM Users) AS People
        ON Users.UID=People.UID
        WHERE People.EmployeeType='F' AND UserStatus='A' ORDER BY People.LastName;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct advisor list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Add each faculty member to the associative array.
    $AdvisorNames[""] = "";
    while($Fields = $SQLResult->fetch_row() )
        $AdvisorNames[$Fields[0]] = $Fields[1]." ".$Fields[2];


    //Initialize all the form data variables. These will be filled in later if a VendorID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.

    $SpnID = 0;

    $FirstName = "";
    $LastName = "";
    $RUID = "";
    $Email = "";
    $StartDate = "";
    $EndDate = "";
    $EmployerName = "";
    $EmployerAddress = "";
    $Location = "";
    $JobTitle = "";
    $JobDetail = "";
    $JobHours = "";
    $Check = "";
    $Semester = "";


	//The pass-through search fields have to be initialized to avoid undefined variable warnings when this page is jumped to from outside TeamsCreateList



    //If there is a query string, fetch team data from the table. TeamID was already pre-initialized to 0.

    if (isset($_GET["spnid"]))
       	if (!ValidateField("spnid", "0123456789", 4))
		    $SpnID = $_GET["spnid"];


    //If the TeamID is specified, get all the fields for this team. With the change record table, the best approach at the moment
    // is to do a separate query for every field, sorted by timestamp so we just get the most recent field value.
    if ($SpnID != 0) {


        $FirstName = RetrieveField($SpnID, "FirstName");
        $LastName = RetrieveField($SpnID, "LastName");
        $RUID = RetrieveField($SpnID, "RUID");
        $Email = RetrieveField($SpnID, "Email");
        $StartDate = RetrieveField($SpnID, "StartDate");
        $EndDate = RetrieveField($SpnID, "EndDate");
        $EmployerName = RetrieveField($SpnID, "EmployerName");
        $EmployerAddress = RetrieveField($SpnID, "EmployerAddress");
        $Location = RetrieveField($SpnID, "Location");
        $JobTitle = RetrieveField($SpnID, "JobTitle");
        $JobDetail = RetrieveField($SpnID, "JobDetail");
        $JobHours = RetrieveField($SpnID, "JobHours");
        $Check = RetrieveField($SpnID, "Check");
        if ($Check == "T")
		    $Check = "checked";
        $Semester = RetrieveField($SpnID, "Semester");

    }


    echo "<FORM METHOD=\"POST\" ACTION=\"SpnsStaffProcessForm.php\" enctype=\"multipart/form-data\">";

    //The create/update button.
	echo "<P>";
    if ($SpnID != 0)
	    printf("<INPUT TYPE=\"SUBMIT\" VALUE=\"Update Spn\">");
    else
	    printf("<INPUT TYPE=\"SUBMIT\" VALUE=\"Create Spn\">");
	echo " Back to <A HREF=\"SpnsStaffCreateList.php\">spn request table</A>";
	echo "</P>";

    echo "<P>";
    if ($Check != "")
        echo "<FONT COLOR=\"RED\"><H4>This student has obtained credit for Internship before</H3></FONT>";

    //For new teams, user has to provide season/year/number.
    //For existing teams, hide season/year/number in the form to avoid having to query the same info in the form processor.



    echo "<STRONG>First Name</STRONG>\r\n<INPUT TYPE=\"text\" NAME=\"firstname\" VALUE=\"".$FirstName."\" SIZE=\"12\" MAXLENGTH=\"80\">\r\n";
    echo "<STRONG>Last Name</STRONG>\r\n<INPUT TYPE=\"text\" NAME=\"lastname\" VALUE=\"".$LastName."\" SIZE=\"12\" MAXLENGTH=\"80\"><BR>\r\n";
    echo "<STRONG>RUID</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"ruid\" VALUE=\"".$RUID."\" SIZE=\"9\" MAXLENGTH=\"9\"><BR>\r\n";
    echo "<STRONG>Rutgers Email Address</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"email\" VALUE=\"".$Email."\" SIZE=\"40\" MAXLENGTH=\"80\"><BR>\r\n";
    echo "<STRONG>Upload your CV/Resume</STRONG><input type=\"file\" name=\"resume\" id=\"resume\"><BR>\r\n";
    echo "<STRONG>Upload your Offer Letter</STRONG><input type=\"file\" name=\"offer\" id=\"offer\"><BR>\r\n";
    echo "<STRONG>Internship start date</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"startdate\" VALUE=\"".$StartDate."\" SIZE=\"5\" MAXLENGTH=\"5\"><BR>\r\n";
    echo "<STRONG>Internship end date</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"enddate\" VALUE=\"".$EndDate."\" SIZE=\"5\" MAXLENGTH=\"5\"><BR>\r\n";
    echo "<STRONG>Employer name</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"employername\" VALUE=\"".$EmployerName."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "<STRONG>Employer address</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"employeraddress\" VALUE=\"".$EmployerAddress."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "<STRONG>Location where you perform your internship</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"location\" VALUE=\"".$Location."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "<STRONG>Job title</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"jobtitle\" VALUE=\"".$JobTitle."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "<STRONG>Please provide job responsibilities for work you expect to do</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"jobdetail\" VALUE=\"".$JobDetail."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "<STRONG>How many hours per week you expect to work?</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"jobhours\" VALUE=\"".$JobHours."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "<INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"check\" ".$Check."> <STRONG>Have you obtained credit for Internship before?</STRONG><BR>\r\n";
    echo "<STRONG>If yes to the last question, provide which semester you obtained a credit? (example: Spring 2021)</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"semester\" VALUE=\"".$Semester."\" SIZE=\"80\" MAXLENGTH=\"100\"><BR>\r\n";
    echo "</P>";

	//Go through through the advisor list and put a drop-down for each one.




    //If this is an existing team store the ID in a hidden field.
    if ($SpnID != 0) {
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"spnid\"           VALUE=\"".$SpnID."\">\r\n";
    }


    //Display the existing notes and change records. Notes are just another row in the change record table.


    //Additional notes.
    echo "<P><STRONG>Additional Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";

    //The create/update button.
    if ($SpnID != 0)
	    printf("<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Spn Request\"></P>");
    else
	    printf("<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Spn Request\"></P>");

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
    //Function to write a string to the log. I've never had this many problems writing a log file. Make sure directory permissions are 775! Must use absolute path!
    // Let apache create the file. Owner will be apache:ece
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Teams/SpnsStaffCreateForm.log"); 
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


    //Function to retrieve a specified field for a specified team.
    function RetrieveField($UID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM SpnRecords WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve team field from database: ".$mysqli->error."</DIV>";
		    return "";
	    }

        //Not all fields are defined for every vendor, so it is not an error for the field to not be found.
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