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
                require('../template/footer.html');
                require('../template/foot.php');
				goto CloseSQL;
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
        require('../template/footer.html');
        require('../template/foot.php');
		goto CloseSQL;
	}


    //Lookup the user to get their access role.
    //SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='wine' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;
    $SQLQuery = "SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$UserNetID."' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    	require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for user ".$UserNetID." has failed: ".$mysqli->error."</DIV>";
        require('../template/footer.html');
        require('../template/foot.php');
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows < 1) {
   		WriteLog("User with NetID ".$UserNetID." not found.");
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User ".$UserNetID." not found.</DIV>";
        require('../template/footer.html');
        require('../template/foot.php');
		goto CloseSQL;
	}

    //Extract the user's role. S=Student, E=ECE Staff, F=ECE Faculty, A=SOE Approver, O=External
	$Fields = $SQLResult->fetch_row();
    $UserDoingUpdateUID = $Fields[0];
    $UserDoingUpdateAccessRole = $Fields[1];


    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
	    require('../template/head.php');
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to export users.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
        require('../template/footer.html');
        require('../template/foot.php');
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
      "F"=>"Faculty",
	  "A"=>"Adjunct",
	  "N"=>"Non-Tenure",
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


    //This had to be done to get rid of all the undefined variable warnings.
    // Trying to preset some of the check boxes doesn't work - then you can never un-check them..
	$UserIDSearch = "";
    $StatusCheck = "";
    $StatusSearch = "";
    $NetIDCheck = "";
    $NetIDSearch = "";
    $RUIDCheck = "";
    $RUIDSearch = "";
    $NameCheck = "";
    $NameSearch = "";
    $GenderCheck = "";
    $GenderSearch = "";
    $ClassCheck = "";
    $ClassSearch = "";
    $StudentTypeCheck = "";
    $StudentTypeSearch = "";
    $EmployeeTypeCheck = "";
    $EmployeeTypeSearch = "";
    $MajorCheck = "";
    $MajorSearch = "";
    $TrackCheck = "";
    $TrackSearch = "";
    $JudgeCheck = "";
    $MissingCheck = "";
    $Sort1 = "";
    $Sort2 = "";



    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.
	if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

    if (isset($_GET["statussearch"]))
	    if (!ValidateField("statussearch", "AGLSRED", 10))
			$StatusSearch = $_GET["statussearch"];

    if (isset($_GET["netidcheck"]))
       	if (!ValidateField("netidcheck", "checked", 7))
		    $NetIDCheck = $_GET["netidcheck"];

    if (isset($_GET["netidsearch"]))
        if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 20))
    		$NetIDSearch = $_GET["netidsearch"];

    if (isset($_GET["ruidcheck"]))
       	if (!ValidateField("ruidcheck", "checked", 7))
		    $RUIDCheck = $_GET["ruidcheck"];

    if (isset($_GET["ruidsearch"]))
        if (!ValidateField("ruidsearch", "0123456789", 10))
    		$RUIDSearch = $_GET["ruidsearch"];

    if (isset($_GET["namecheck"]))
       	if (!ValidateField("namecheck", "checked", 7))
		    $NameCheck = $_GET["namecheck"];

    if (isset($_GET["namesearch"]))
	    if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 50))
            $NameSearch = $_GET["namesearch"];

    if (isset($_GET["gendercheck"]))
       	if (!ValidateField("gendercheck", "checked", 7))
		    $GenderCheck = $_GET["gendercheck"];

    if (isset($_GET["gendersearch"]))
	    if (!ValidateField("gendersearch", "MFZ", 10))
			$GenderSearch = $_GET["gendersearch"];

    if (isset($_GET["classcheck"]))
       	if (!ValidateField("classcheck", "checked", 7))
		    $ClassCheck = $_GET["classcheck"];

    if (isset($_GET["classsearch"]))
	    if (!ValidateField("classsearch", "0123456789", 10))
			$ClassSearch = $_GET["classsearch"];

    if (isset($_GET["studenttypecheck"]))
       	if (!ValidateField("studenttypecheck", "checked", 7))
		    $StudentTypeCheck = $_GET["studenttypecheck"];

    if (isset($_GET["studenttypesearch"]))
	    if (!ValidateField("studenttypesearch", "UMWPN", 10))
			$StudentTypeSearch = $_GET["studenttypesearch"];

    if (isset($_GET["employeetypecheck"]))
       	if (!ValidateField("employeetypecheck", "checked", 7))
		    $EmployeeTypeCheck = $_GET["employeetypecheck"];

    if (isset($_GET["employeetypesearch"]))
	    if (!ValidateField("employeetypesearch", "SFANTGWPERH", 10))
			$EmployeeTypeSearch = $_GET["employeetypesearch"];

    if (isset($_GET["majorcheck"]))
       	if (!ValidateField("majorcheck", "checked", 7))
		    $MajorCheck = $_GET["majorcheck"];

    if (isset($_GET["majorsearch"]))
	    if (!ValidateField("majorsearch", "0123456789", 3))
			$MajorSearch = $_GET["majorsearch"];

    if (isset($_GET["trackcheck"]))
       	if (!ValidateField("trackcheck", "checked", 7))
		    $TrackCheck = $_GET["trackcheck"];

    if (isset($_GET["tracksearch"]))
	    if (!ValidateField("tracksearch", "AB", 10))
			$TrackSearch = $_GET["tracksearch"];

    if (isset($_GET["judgecheck"]))
       	if (!ValidateField("judgecheck", "checked", 7))
		    $JudgeCheck = $_GET["judgecheck"];

    if (isset($_GET["missingcheck"]))
       	if (!ValidateField("missingcheck", "checked", 7))
		    $MissingCheck = $_GET["missingcheck"];

    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort1 = $_GET["sort1"];

    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ", 50))
		    $Sort2 = $_GET["sort2"];


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
    $SQLQuery = "SELECT Users.UID, Users.MissingFlag, User.NetID, User.RUID, User.FirstName, User.LastName, User.Gender, User.PreferredEmail, User.DepartureDateUTC, User.StudentType, User.EmployeeType, User.UserStatus, User.Major, User.Track, User.PotentialJudge FROM Users
        LEFT JOIN
            (SELECT UID,
                (SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
                (SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID,
                (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
                (SELECT Value FROM UserRecords WHERE Field='Gender' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Gender,
                (SELECT Value FROM UserRecords WHERE Field='OfficialEmail' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PreferredEmail,
                (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC,
                (SELECT Value FROM UserRecords WHERE Field='StudentType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS StudentType,
                (SELECT Value FROM UserRecords WHERE Field='EmployeeType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS EmployeeType,
                (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS UserStatus,
                (SELECT Value FROM UserRecords WHERE Field='Major' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Major,
                (SELECT Value FROM UserRecords WHERE Field='Track' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Track,
                (SELECT Value FROM UserRecords WHERE Field='PotentialJudge' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS PotentialJudge
            FROM Users) AS User
        ON Users.UID=User.UID
        ".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to export list: ".$mysqli->error."</DIV>";
        require('../template/footer.html');
        require('../template/foot.php');
		goto CloseSQL;
    }

	//This is what triggers the WEB browswer to save the returned text as a CSV file.
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="UsersExport.csv"');

    //CSV File header.
    echo "Status,NetID,RUID,FirstName,LastName,Gender,Email,DepartureDate,Type,Department\r\n";

    //Export the rows.
    if ($SQLResult->num_rows > 0) {

        while($Fields = $SQLResult->fetch_assoc()) {

            echo "\"".$StatusList[$Fields["UserStatus"]]."\",";
            echo "\"".$Fields["NetID"]."\",";
            echo "\"".$Fields["RUID"]."\",";
            echo "\"".$Fields["FirstName"]."\",";
            echo "\"".$Fields["LastName"]."\",";
            echo "\"".$GenderList[$Fields["Gender"]]."\",";
            echo "\"".$Fields["PreferredEmail"]."\",";
			if ($Fields["DepartureDateUTC"] != "")
	            echo "\"".date("m/d/Y", $Fields["DepartureDateUTC"])."\",";
			else
				echo ",";
            echo "\"".$StudentTypeList[$Fields["StudentType"]]." ".$EmployeeTypeList[$Fields["EmployeeType"]]."\",";
            echo "\"".$Fields["Major"]." ".$TrackList[$Fields["Track"]]."\"\r\n";
        }
    }

CloseSQL:
    //Done with SQL server.
    $mysqli->close();

?>


<?php
    //Function to write a string to the log. I've never had this many problems writing a log file. Make sure directory permissions are 775! Must use absolute path!
    // Let apache create the file. Owner will be apache:ece
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersCreateExport.log"); 
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
