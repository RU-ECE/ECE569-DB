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

    $SortList = array
      (
      ""=>"",
	  "CreateTime"=>"Member Created",
      "Number"=>"Team Number",
      "LastName"=>"Name",
	  "Type"=>"Member Type"
      );


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


    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.
    //Setup the default button states based on form fields 
	$MemberID = "";
    if (isset($_GET["mid"]))
        if (!ValidateField("mid", "0123456789", 1))
			$MemberID = $_GET["mid"];

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

    //$SemesterSearch = "";
    if (isset($_GET["semestersearch"]))
       	if (!ValidateField("semestersearch", "SMF", 1))
   		    $SemesterSearch = $_GET["semestersearch"];

    //$YearSearch = "";
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

    $Sort1 = "Number";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"MembersCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only member status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD><TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"typecheck\"".$TypeCheck."> Show only type "; DropdownListBox("typesearch", $TypeList, $TypeSearch); echo "</TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"semestercheck\"".$SemesterCheck."> Show only semester "; DropdownListBox("semestersearch", $SemesterList, $SemesterSearch); echo "<INPUT TYPE=\"text\" NAME=\"yearsearch\" VALUE=\"".$YearSearch."\" SIZE=\"4\" MAXLENGTH=\"4\">"; echo "-<INPUT TYPE=\"text\" NAME=\"teamsearch\" VALUE=\"".$TeamSearch."\" SIZE=\"3\" MAXLENGTH=\"3\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\"".$NetIDCheck."> Show only NetID <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"></TD></TR>\r\n";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD></TR>\r\n";
    echo "</TBODY></TABLE>";

	//Check if this is a search for a specific request, and if the ID is zero.
	if ($MemberID == "0") {
        echo "0 Members found.<BR>";
		goto CloseSQL;
	}

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (Status='".mysqli_real_escape_string($mysqli, $StatusSearch)."')";
    if ($TypeCheck && $TypeSearch)
	    $Conditions .= "AND (Type='".mysqli_real_escape_string($mysqli, $TypeSearch)."')";
    if ($SemesterCheck && $SemesterSearch)
	    $Conditions .= "AND (Semester='".mysqli_real_escape_string($mysqli, $SemesterSearch)."') ";
    if ($SemesterCheck && $YearSearch)
	    $Conditions .= "AND (Year='".mysqli_real_escape_string($mysqli, $YearSearch)."') ";
    if ($SemesterCheck && $TeamSearch)
	    $Conditions .= "AND (Number='".mysqli_real_escape_string($mysqli, $TeamSearch)."') ";
    if ($NetIDCheck && $NetIDSearch)
	    $Conditions .= "AND (NetID='".mysqli_real_escape_string($mysqli, $NetIDSearch)."') ";

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
	$SQLQuery = "SELECT Members.MID, Member.Status, Member.Type, Member.UserID, Member.TeamID, User.FirstName, User.LastName, User.NetID, Team.Semester, Team.Year, Team.Number FROM Members
		LEFT JOIN
			(SELECT MID,
				(SELECT Value FROM MemberRecords WHERE Field='Status' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				(SELECT Value FROM MemberRecords WHERE Field='Type' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS Type,
				(SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS TeamID,
				(SELECT Value FROM MemberRecords WHERE Field='UserID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS UserID
			FROM Members) AS Member
		ON Member.MID=Members.MID
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID
			FROM Users) AS User
		ON User.UID=Member.UserID
		LEFT JOIN
			(SELECT TID, Semester, Year, Number FROM Teams) AS Team
		ON Team.TID=Member.TeamID
 		".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get Members list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    $TotalCost = 0;
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." Members found.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>Type</TH><TH>Team</TH><TH>NetID</TH><TH>Name</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            echo "<TR>";
            echo "<TD><A HREF=\"MembersCreateForm.php?mid=".$Fields["MID"]."&statuscheck=".$StatusCheck."&statussearch=".urlencode($StatusSearch)."&typecheck=".$TypeCheck."&typesearch=".urlencode($TypeSearch)."&semestercheck=".$SemesterCheck."&semestersearch=".urlencode($SemesterSearch)."&yearsearch=".urlencode($YearSearch)."&teamsearch=".urlencode($TeamSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".$StatusList[$Fields["Status"]]."</TD>";
            echo "<TD>".$TypeList[$Fields["Type"]]."</A></TD>";
            echo "<TD>".$Fields["Semester"].substr($Fields["Year"], -2)."-".$Fields["Number"]."</TD>";
            echo "<TD>".$Fields["NetID"]."</TD>";
            echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>";
            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." Members found.<BR>";
    }
    else 
        echo "0 Members found.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Members/MembersCreateList.log"); 
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
CREATE TABLE `Members` (
  `MID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT
);

CREATE TABLE `MemberRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO Members (CreateTime) VALUES (1685106110);
INSERT INTO MemberRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "Status", "A");
INSERT INTO MemberRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1685106110, "UserID", "1273");

Updater:
 ContactID changes to ContactMID
 Delete old ContactID
 StudentX/AdvisorX changes to row in Members table
 Remove StudentX/AdvisorX from TeamRecords
 Delete old StudentX/AdvisorX records


Attempts to show the member change records in teams.
SELECT * FROM (SELECT MID, (SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1)) FROM Members AS Member LEFT JOIN MemberRecords ON MemberRecords.ID=Members.MID WHERE Member.TeamID=27;
SELECT MID, (SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS TeamID FROM Members WHERE (SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1)=27;
SELECT Members.MID, Member.TeamID FROM Members LEFT JOIN (SELECT MID, (SELECT Value FROM MemberRecords WHERE Field='TeamID' AND ID=Members.MID ORDER BY CreateTime DESC LIMIT 1) AS TeamID FROM Members) AS Member ON Member.MID=Te;

-->
