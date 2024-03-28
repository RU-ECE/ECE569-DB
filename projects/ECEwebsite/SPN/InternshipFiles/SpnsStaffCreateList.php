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

    //Extract the user's role. D=Student, S=ECE Staff, F=ECE Faculty, A=SOE Approver, O=External
	$Fields = $SQLResult->fetch_row();
    $UserID = $Fields[0];
    $UserDoingUpdateAccessRole = $Fields[1];


	//This has to go out after the user is authenticated and their role is determined, because the menus are based on role.
	require('../template/head.php');

	//This is needed later to figure out if the student has graduated or not.
	$CurrentTime = time();

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Page content starts here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
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

    $SortList = array
      (
      ""=>"",
      "Number"=>"Team Number",
      "AdvisorID1"=>"Team Advisor",
      "CAST(Locker AS integer)"=>"Locker Number"
      );


    //This had to be done to get rid of all the undefined variable warnings.
    // Trying to preset some of the check boxes doesn't work - then you can never un-check them..
    $StatusCheck = "";
    $StatusSearch = "";
	$TeamCheck = "";
    $TeamYearSearch = "";
    $TeamNumberSearch = "";
    $TeamSeasonSearch = "";
    $AdvisorCheck = "";
    $AdvisorSearch = "";
	$LockerCheck = "";
    $Sort1 = "Number";
    $Sort2 = "";

    $TeamYearSearch = date('Y', $CurrentTime) - 2000;
    if ( date('n', $CurrentTime) > 5)
        $TeamSeasonSearch = "F";
    else
	    $TeamSeasonSearch = "S";


    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.
    //Setup the default button states based on form fields 
    if (isset($_GET["statuscheck"]))
	    if ($_GET["statuscheck"] != "")
		    $StatusCheck = "checked";

    if (isset($_GET["statussearch"]))
        if (!ValidateField("statussearch", "FPBOVC", 1))
               $StatusSearch = $_GET["statussearch"];

    if (isset($_GET["teamcheck"]))
	    if ($_GET["teamcheck"] != "")
		    $TeamCheck = "checked";

    if (isset($_GET["teamseasonsearch"]))
       	if (!ValidateField("teamseasonsearch", "SF", 1))
   		    $TeamSeasonSearch = $_GET["teamseasonsearch"];

    if (isset($_GET["teamyearsearch"]))
       	if (!ValidateField("teamyearsearch", "0123456789", 2))
   		    $TeamYearSearch = $_GET["teamyearsearch"];

    if (isset($_GET["teamnumbersearch"]))
       	if (!ValidateField("teamnumbersearch", "0123456789", 2))
   		    $TeamNumberSearch = $_GET["teamnumbersearch"];

    if (isset($_GET["advisorcheck"]))
	    if ($_GET["advisorcheck"] != "")
		    $AdvisorCheck = "checked";

    if (isset($_GET["advisorsearch"]))
       	if (!ValidateField("advisorsearch", "0123456789", 3))
   		    $AdvisorSearch = $_GET["advisorsearch"];

    if (isset($_GET["lockercheck"]))
	    if ($_GET["lockercheck"] != "")
		    $LockerCheck = "checked";

    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


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
    $AdvisorList[""] = "";
    while($Fields = $SQLResult->fetch_row() )
        $AdvisorList[$Fields[0]] = $Fields[1]." ".$Fields[2];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"TeamsStaffCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only team status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD><TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"teamcheck\"".$TeamCheck."> Show only team "; DropdownListBox("teamseasonsearch", $TeamSeasonList, $TeamSeasonSearch); echo "<INPUT TYPE=\"text\" NAME=\"teamyearsearch\" VALUE=\"".$TeamYearSearch."\" SIZE=\"2\" MAXLENGTH=\"2\">-<INPUT TYPE=\"text\" NAME=\"teamnumbersearch\" VALUE=\"".$TeamNumberSearch."\" SIZE=\"2\" MAXLENGTH=\"2\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"advisorcheck\"".$AdvisorCheck."> Show only teams advised by "; DropdownListBox("advisorsearch", $AdvisorList, $AdvisorSearch); echo "</TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"lockercheck\"".$LockerCheck."> Show only teams with a locker assigned.</TD></TR>";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD></TR>";
    echo "</TBODY></TABLE>";

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (Status='".mysqli_real_escape_string($mysqli, $StatusSearch)."')";
    if ($TeamCheck && $TeamSeasonSearch)
	    $Conditions .= "AND (Season='".$TeamSeasonSearch."') ";
    if ($TeamCheck && $TeamYearSearch)
	    $Conditions .= "AND (Year='".$TeamYearSearch."') ";
    if ($TeamCheck && $TeamNumberSearch)
	    $Conditions .= "AND (Number='".$TeamNumberSearch."') ";
    if ($AdvisorCheck && $AdvisorSearch)
	    $Conditions .= "AND (AdvisorID1='".$AdvisorSearch."') ";
    if ($LockerCheck)
	    $Conditions .= "AND (Locker!='') ";

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
    $SQLQuery = "SELECT Spns.SID, Spn.FirstName, Spn.LastName, Spn.RUID, Spn.Email FROM Spns
        LEFT JOIN
            (SELECT SID,
                (SELECT Value FROM SpnRecords WHERE Field='FirstName' AND ID=Spns.SID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM SpnRecords WHERE Field='LastName' AND ID=Spns.SID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM SpnRecords WHERE Field='RUID' AND ID=Spns.SID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
                (SELECT Value FROM SpnRecords WHERE Field='Email' AND ID=Spns.SID ORDER BY CreateTime DESC LIMIT 1) AS Email
            FROM Spns) AS Spn
        ON Spns.SID=Spn.SID;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get spns list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    $TotalCost = 0;
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." spns request found. Create a <A HREF=\"https://apps.ece.rutgers.edu/Spns/SpnsStaffCreateForm.php?spnid=0\">new spn</A>.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Applicant Name</TH><TH>RUID</TH><TH>Email</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            echo "<TR>";
//            echo "<TD><A HREF=\"TeamsStaffCreateForm.php?teamid=".$Fields["TID"]."\">".$Fields["Season"].$Fields["Year"]."-".$Fields["Number"]."</A></TD>";
            echo "<TD><A HREF=\"SpnsStaffCreateForm.php?spnid=".$Fields["SID"]."\">".$Fields["FirstName"]."-".$Fields["LastName"]."</A></TD>";
            echo "<TD>".$Fields["RUID"]."</TD>";
            echo "<TD>".$Fields["Email"]."</TD>";

            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." spns request found. Create a <A HREF=\"https://apps.ece.rutgers.edu/Spns/SpnsStaffCreateForm.php?spnid=0\">new spn request</A>.<BR>";
    }
    else 
        echo "0 teams found. Create a <A HREF=\"https://apps.ece.rutgers.edu/Spns/SpnsStaffCreateForm.php?teamid=0\">new spn request</A>.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Teams/SpnsStaffCreateList.log"); 
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
CREATE TABLE `Teams` (
  `TID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT,
  `Season` VARCHAR(1),
  `Year` INT,
  `Number` INT,
  UNIQUE (`Number`,`Year`,`Season`)
);

CREATE TABLE `TeamRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO Teams (CreateTime, Season, Year, Number) VALUES (1644439134, "S", 22, 7);
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Status", "A");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439250, "Student1", "326");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439350, "Student2", "143");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439450, "Student3", "512");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439550, "AdvisorID1", "23");
INSERT INTO Teams (CreateTime, Season, Year, Number) VALUES (1644439134, "S", 22, 5);
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644441150, "Status", "A");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644441150, "Student1", "859");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644441150, "Student2", "732");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644441150, "Student3", "701");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644441150, "AdvisorID1", "27");

SELECT @StudentID:=Users.UID FROM Users LEFT JOIN (SELECT UID, (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName FROM Users) AS People ON Users.UID=People.UID WHERE People.FirstName LIKE 'Sean' AND People.LastName LIKE 'Hewlett';
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (36, 51, 1645813000, "ContactID", @StudentID);

Missing 9
Missing 22
Missing 30
Missing 34
Missing 46


INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (34, 51, 1646084000, "Locker", "1");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (34, 51, 1646084000, "Combo", "3426");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (47, 51, 1646084000, "Locker", "4");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (47, 51, 1646084000, "Combo", "3788");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (7, 51, 1646084000, "Locker", "16");
INSERT INTO TeamRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (7, 51, 1646084000, "Combo", "2561");


-->
