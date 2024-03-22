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
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit an SPN.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for SPN status dropdown list box
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

    $SortList = array
      (
      ""=>"",
	  "CreateTime"=>"SPN Created",
      "Course"=>"Course Number",
      "Section"=>"Section Number",
	  "SPN"=>"SPN"
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
	$SPNID = "";
    if (isset($_GET["spnid"]))
        if (!ValidateField("spnid", "0123456789", 1))
			$SPNID = $_GET["spnid"];

	$StatusCheck = "";
    if (isset($_GET["statuscheck"]))
	    if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
    if (isset($_GET["statussearch"]))
        if (!ValidateField("statussearch", "AUI", 1))
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

    //$SemesterSearch = "";
    if (isset($_GET["semestersearch"]))
       	if (!ValidateField("semestersearch", "SMF", 1))
   		    $SemesterSearch = $_GET["semestersearch"];

    //$YearSearch = "";
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

    $Sort1 = "SID";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"SPNCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only SPN status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD><TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"typecheck\"".$TypeCheck."> Show only type "; DropdownListBox("typesearch", $TypeList, $TypeSearch); echo "</TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"semestercheck\"".$SemesterCheck."> Show only semester "; DropdownListBox("semestersearch", $SemesterList, $SemesterSearch); echo "<INPUT TYPE=\"text\" NAME=\"yearsearch\" VALUE=\"".$YearSearch."\" SIZE=\"4\" MAXLENGTH=\"4\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"coursecheck\"".$CourseCheck."> Show only SPNs for course number <INPUT TYPE=\"text\" NAME=\"coursesearch\" VALUE=\"".$CourseSearch."\" SIZE=\"3\" MAXLENGTH=\"3\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"sectioncheck\"".$SectionCheck."> Show only SPNs for section number <INPUT TYPE=\"text\" NAME=\"sectionsearch\" VALUE=\"".$SectionSearch."\" SIZE=\"2\" MAXLENGTH=\"2\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"spncheck\"".$SPNCheck."> Show only SPN <INPUT TYPE=\"text\" NAME=\"spnsearch\" VALUE=\"".$SPNSearch."\" SIZE=\"10\" MAXLENGTH=\"10\"></TD></TR>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\"".$NetIDCheck."> Show only NetID <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"></TD></TR>";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD></TR>";
    echo "</TBODY></TABLE>";

	//Check if this is a search for a specific request, and if the ID is zero.
	if ($SPNID == "0") {
        echo "0 SPNs found.<BR>";
		goto CloseSQL;
	}

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (Status='".mysqli_real_escape_string($mysqli, $StatusSearch)."')";
    if ($SemesterCheck && $SemesterSearch)
	    $Conditions .= "AND (Semester='".mysqli_real_escape_string($mysqli, $SemesterSearch)."') ";
    if ($SemesterCheck && $YearSearch)
	    $Conditions .= "AND (Year='".mysqli_real_escape_string($mysqli, $YearSearch)."') ";
    if ($CourseCheck && $CourseSearch)
	    $Conditions .= "AND (Course='".mysqli_real_escape_string($mysqli, $CourseSearch)."') ";
    if ($SectionCheck && $SectionSearch)
	    $Conditions .= "AND (Section='".mysqli_real_escape_string($mysqli, $SectionSearch)."') ";
    if ($SPNCheck && $SPNSearch)
	    $Conditions .= "AND (SPN='".mysqli_real_escape_string($mysqli, $SPNSearch)."') ";
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
    $SQLQuery = "SELECT SPNs.SID, SPNs.CreateTime, SPNs.Type, SPNs.Semester, SPNs.Year, SPNs.Course, SPNs.Section, SPNs.SPN, SPN.Status, SPN.UserID, User.NetID, User.FirstName, User.LastName FROM SPNs
        LEFT JOIN
            (SELECT SID,
                (SELECT Value FROM SPNRecords WHERE Field='Status' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS Status,
                (SELECT Value FROM SPNRecords WHERE Field='UserID' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS UserID
            FROM SPNs) AS SPN
        ON SPNs.SID=SPN.SID
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName
			FROM Users) AS User
		ON User.UID=SPN.UserID
 		".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get SPN list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    $TotalCost = 0;
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." SPNs found.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>SPN</TH><TH>Type</TH><TH>Semester/Year</TH><TH>Course</TH><TH>Section</TH><TH>Name/NetID</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            echo "<TR>";
            echo "<TD><A HREF=\"SPNCreateForm.php?spnid=".$Fields["SID"]."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&semestercheck=".$SemesterCheck."&semestersearch=".$SemesterSearch."&yearsearch=".$YearSearch."&coursecheck=".$CourseCheck."&coursesearch=".urlencode($CourseSearch)."&sectioncheck=".$SectionCheck."&sectionsearch=".urlencode($SectionSearch)."&spncheck=".$SPNCheck."&spnsearch=".urlencode($SPNSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".$StatusList[$Fields["Status"]]."</TD>";
            echo "<TD>".$Fields["SPN"]."</A></TD>";
            echo "<TD>".$TypeList[$Fields["Type"]]."</TD>";
            echo "<TD>".$SemesterList[$Fields["Semester"]]." ".$Fields["Year"]."</TD>";
            echo "<TD>".$Fields["Course"]."</TD>";
            echo "<TD>".$Fields["Section"]."</TD>";
			if ($Fields["NetID"] != "")
	            echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"].", ".$Fields["NetID"]."</TD>";
			else
				echo "<TD>&nbsp;</TD>";
            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." SPNs found.<BR>";
    }
    else 
        echo "0 SPNs found.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNCreateList.log"); 
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
CREATE TABLE `SPNs` (
  `SID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT,
  `Type` VARCHAR(1),
  `Semester` VARCHAR(1),
  `Year` VARCHAR(4),
  `Course` VARCHAR(3),
  `Section` VARCHAR(2),
  `SPN` VARCHAR(10),
  UNIQUE(Semester, Year, Course, Section, SPN)
);

CREATE TABLE `SPNRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO SPNs (CreateTime, Type, Semester, Year, Course, Section, SPN) VALUES (1644439134, "U", "F", "2023", "222", "01", "381209");
INSERT INTO SPNRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1644439150, "Status", "A");
INSERT INTO SPNs (CreateTime, Type, Semester, Year, Course, Section, SPN) VALUES (1644439134, "U", "F", "2023", "221", "01", "348939");
INSERT INTO SPNRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (2, 51, 1644439150, "Status", "A");
INSERT INTO SPNs (CreateTime, Type, Semester, Year, Course, Section, SPN) VALUES (1644439134, "U", "F", "2023", "230", "02", "200053");
INSERT INTO SPNRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (3, 51, 1644439150, "Status", "A");



SPNReqStaffCreateList.php
SPNReqCreateForm.php
SPNReqProcessForm.php
SPNReqStudentCreateList.php
SPNCreateForm.php
SPNProcessForm.php
SPNCreateList.php

//The remaining available SPNs
SELECT SPNs.Course, SPNs.Section, COUNT(SPNs.SID) AS Remaining, SPNs.Type, SPNs.Semester, SPNs.Year, SPN.Status FROM SPNs LEFT JOIN (SELECT SID, (SELECT Value FROM SPNRecords WHERE Field='Status' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS Status FROM SPNs) AS SPN ON SPNs.SID=SPN.SID WHERE Status='A' AND Semester='F' AND Year=2023 AND Type='U' GROUP BY Course, Section ORDER BY Remaining;
//Just with 6 or less remaining.
SELECT SPNs.Course, SPNs.Section, COUNT(SPNs.SID) AS Remaining, SPNs.Type, SPNs.Semester, SPNs.Year, SPN.Status FROM SPNs LEFT JOIN (SELECT SID, (SELECT Value FROM SPNRecords WHERE Field='Status' AND ID=SPNs.SID ORDER BY CreateTime DESC LIMIT 1) AS Status FROM SPNs) AS SPN ON SPNs.SID=SPN.SID WHERE Status='A' AND Semester='F' AND Year=2023 AND Type='U' GROUP BY Course, Section HAVING Remaining<7;

-->
