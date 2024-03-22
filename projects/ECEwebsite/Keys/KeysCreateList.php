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
    $Title = "Keys";
    $Menu = "Keys";
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
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for item status dropdown list box
    $StatusList = array
       (
       "L"=>"Loaned",
       "M"=>"Missing",
       "D"=>"Destroyed",
       "R"=>"Returned",
       "U"=>"Unknown Door",
       "X"=>"Door Removed"
       );

    $SortList = array
      (
      ""=>"",
      "RoomNumber"=>"Room Number",
      "NetID"=>"NetID",
      "LastName"=>"Last Name",
      "KeyCode"=>"Key Code",
      "KeyNumber"=>"Key Number",
      "DepartureDateUTC"=>"Graduation Date"
      );


    //This had to be done to get rid of all the undefined variable warnings.
    // Trying to preset some of the check boxes doesn't work - then you can never un-check them..
	$KeyIDSearch = "";
    $StatusCheck = "";
    $StatusSearch = "S";
    $NetIDCheck = "";
    $NetIDSearch = "";
    $NameCheck = "";
    $NameSearch = "";
    $RoomNumberCheck = "";
    $RoomNumberSearch = "";
    $KeyCodeCheck = "";
    $KeyCodeSearch = "";
    $Sort1 = "RoomNumber";
    $Sort2 = "";


    //We need a list of all the rooms for the dropdown selection.
    $SQLQuery = "SELECT RoomNumber, RoomName, KeyCode FROM Rooms ORDER BY RoomNumber;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct room list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Add each room to the associative array.
	$RoomNumberList[""] = "";
    while($Fields = $SQLResult->fetch_row() )
		$RoomNumberList[$Fields[0]] = $Fields[1]." (".$Fields[2].")";


    //Setup the default button states based on form fields 
    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.

    if (isset($_GET["keyid"]))
	    if (!ValidateField("keyid", "0123456789", 10))
            $KeyIDSearch = $_GET["keyid"];

    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

    if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "LMDRUX", 5))
		    $StatusSearch = $_GET["statussearch"];

    if (isset($_GET["netidcheck"]))
        if (!ValidateField("netidcheck", "checked", 7))
			$NetIDCheck = $_GET["netidcheck"];

    if (isset($_GET["netidsearch"]))
       	if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 20))
		    $NetIDSearch = $_GET["netidsearch"];

    if (isset($_GET["namecheck"]))
        if (!ValidateField("namecheck", "checked", 7))
			$NameCheck = $_GET["namecheck"];

    if (isset($_GET["namesearch"]))
       	if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-' ", 30))
		    $NameSearch = $_GET["namesearch"];

    if (isset($_GET["roomnumbercheck"]))
        if (!ValidateField("roomnumbercheck", "checked", 7))
			$RoomNumberCheck = $_GET["roomnumbercheck"];

    if (isset($_GET["roomnumbersearch"]))
       	if (!ValidateField("roomnumbersearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 10))
		    $RoomNumberSearch = $_GET["roomnumbersearch"];

    if (isset($_GET["keycodecheck"]))
        if (!ValidateField("keycodecheck", "checked", 7))
			$KeyCodeCheck = $_GET["keycodecheck"];

    if (isset($_GET["keycodesearch"]))
       	if (!ValidateField("keycodesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 10))
   		    $KeyCodeSearch = $_GET["keycodesearch"];

    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 50))
		    $Sort1 = $_GET["sort1"];

    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 50))
		    $Sort2 = $_GET["sort2"];


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"KeysCreateList.php\">";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\"".$StatusCheck."> Show only keys of status "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo "</TD><TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\"".$NetIDCheck."> Show only keys borrowed by NetID <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"namecheck\"".$NameCheck."> Show only keys borrowed by last name <INPUT TYPE=\"text\" NAME=\"namesearch\" VALUE=\"".$NameSearch."\" SIZE=\"20\" MAXLENGTH=\"30\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"roomnumbercheck\"".$RoomNumberCheck."> Show only keys for room number "; DropdownListBox("roomnumbersearch", $RoomNumberList, $RoomNumberSearch); echo "</TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"keycodecheck\"".$KeyCodeCheck."> Show only key code <INPUT TYPE=\"text\" NAME=\"keycodesearch\" VALUE=\"".$KeyCodeSearch."\" SIZE=\"10\" MAXLENGTH=\"10\"></TD></TR>\r\n";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD>\r\n";
    echo "</TBODY></TABLE>";

	//If we are searching for a particular key, and it's the zero key, then find nothing.
	if ($KeyIDSearch == "0") {
		echo "0 keys found.";
		echo "</FORM>";
		goto CloseSQL;
	}

    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND LOCATE(Status,'".mysqli_real_escape_string($mysqli, $StatusSearch)."') ";
    if ($NetIDCheck && $NetIDSearch)
	    $Conditions .= "AND (NetID='".mysqli_real_escape_string($mysqli, $NetIDSearch)."') ";
    if ($NameCheck && $NameSearch)
	    $Conditions .= "AND (LastName='".mysqli_real_escape_string($mysqli, $NameSearch)."') ";
    if ($RoomNumberCheck && $RoomNumberSearch)
	    $Conditions .= "AND (RoomNumber='".mysqli_real_escape_string($mysqli, $RoomNumberSearch)."') ";
    if ($KeyCodeCheck && $KeyCodeSearch)
	    $Conditions .= "AND (KeyCode='".mysqli_real_escape_string($mysqli, $KeyCodeSearch)."') ";

    //Replace the leading "AND" with "WHERE", if any conditions were selected.
    if ($Conditions)
	    $Conditions = "WHERE ".substr($Conditions, 4);

    //Compose the sortings string.
    if ($Sort1)
        $Sorting .= ", ".mysqli_real_escape_string($mysqli, $Sort1);
    if ($Sort2)
        $Sorting .= ", ".mysqli_real_escape_string($mysqli, $Sort2);

    //Replace the leading comma with "ORDER BY", if any sortings were selected.
    if ($Sorting)
	    $Sorting = "ORDER BY ".substr($Sorting, 2);


    //Now we can put together the final query. 
	//Wow.. I never thought this would work..
    $SQLQuery = "SELECT Keys.KID, Key.Status, Key.UserID, Key.RoomNumber, Key.KeyCode, Key.KeyNumber, User.UID, User.FirstName, User.LastName, User.NetID, User.DepartureDateUTC FROM `Keys`
        LEFT JOIN
            (SELECT KID,
                (SELECT Value FROM KeyRecords WHERE Field='Status' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				(SELECT Value FROM KeyRecords WHERE Field='UserID' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS UserID,
				(SELECT Value FROM KeyRecords WHERE Field='RoomNumber' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS RoomNumber,
                (SELECT Value FROM KeyRecords WHERE Field='KeyCode' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyCode,
                (SELECT Value FROM KeyRecords WHERE Field='KeyNumber' AND ID=Keys.KID ORDER BY CreateTime DESC LIMIT 1) AS KeyNumber
            FROM `Keys`) AS `Key`
        ON Keys.KID=Key.KID
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC
			FROM Users) AS User
		ON User.UID=Key.UserID
        ".$Conditions." ".$Sorting.";";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get key list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the item query results in a table if any rows were found.  
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." keys found. <A HREF=\"KeysCreateExport.php?statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&roomnumbercheck=".$RoomNumberCheck."&roomnumbersearch=".urlencode($RoomNumberSearch)."&keycodecheck=".$KeyCodeCheck."&keycodesearch=".urlencode($KeyCodeSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">Export</A> this list.<BR>\r\n";

        echo "<TABLE CLASS=\"table table-striped table-bordered\">\r\n";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>Status</TH><TH>Room#</TH><TH>Key Code</TH><TH>Key#</TH><TH>Name</TH><TH>NetID</TH><TH>Departure</TH></TR>";
        echo "</THEAD>\r\n";
        $ItemsTotal = 0;
        while($Fields = $SQLResult->fetch_assoc()) {
            echo "<TR>\n\r";
            echo "<TD><A HREF=\"KeysCreateForm.php?keyid=".$Fields["KID"]."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&roomnumbercheck=".$RoomNumberCheck."&roomnumbersearch=".urlencode($RoomNumberSearch)."&keycodecheck=".$KeyCodeCheck."&keycodesearch=".urlencode($KeyCodeSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".$StatusList[$Fields["Status"]]."</A></TD>\n\r";
            echo "<TD>".$Fields["RoomNumber"]."</TD>\n\r";
            echo "<TD>".$Fields["KeyCode"]."</TD>\n\r";
            echo "<TD>".$Fields["KeyNumber"]."</TD>\n\r";
            //If the key is loaned out, print who has it and their expected graduation date.
            if ($Fields["Status"] == "L") {
                echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>\n\r";
                echo "<TD><A HREF=\"../Users/UsersCreateForm.php?userid=".$Fields["UID"]."\">".$Fields["NetID"]."</A></TD>\r\n";
                //Graduation date is highlighted if the student is graduating in the next 30 days.
                if ($Fields["DepartureDateUTC"] != "") {
                    if ($CurrentTime > $Fields["DepartureDateUTC"] + (30 * 86400) )
                        echo "<TD CLASS=\"table-danger\">".date("F Y", $Fields["DepartureDateUTC"])."</TD>\r\n";
                    else
                        echo "<TD>".date("F Y", $Fields["DepartureDateUTC"])."</TD>\r\n";
                }
                else
                    echo "<TD>&nbsp;</TD>\r\n";
            }
            else
                echo "<TD>&nbsp;</TD><TD>&nbsp;</TD><TD>&nbsp;</TD>\r\n";
            echo "</TR>\n\r";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." keys found.<BR>\n\r";
    }
    else 
        echo "0 keys found.<BR>\n\r";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Keys/KeysCreateList.log"); 
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
CREATE TABLE `Keys` (
  `KID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT
);

CREATE TABLE `KeyRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

Key Fields:
Status
UserID
RoomNumber
KeyCode
KeyNumber


INSERT INTO `Keys` (CreateTime) VALUES (1646945846);
INSERT INTO KeyRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1646945846, "Status", "L");
INSERT INTO KeyRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1646945846, "UserID", "326");
INSERT INTO KeyRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1646945846, "RoomNumber", "ECE205");
INSERT INTO KeyRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1646945846, "KeyCode", "BEB95");
INSERT INTO KeyRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (1, 51, 1646945846, "KeyNumber", "1");

This makes a list of the number of keys of each keycode, sorted by the number of keys we have.
I can't make it show the room number with every count - not allowed.
SELECT Value, COUNT(*) FROM KeyRecords WHERE Field='KeyCode' GROUP BY Value ORDER BY COUNT(*);


select * from Rooms left join (select Value from KeyRecords where Field='KeyCode') as KeyName on Value=Rooms.KeyCode; Works, but not sure what its producing.
select * from Rooms left join KeyRecords on Value=Rooms.KeyCode and Field='KeyCode';
select * from Rooms join KeyRecords on Rooms.KeyCode=Value and Field='KeyCode';
select * from KeyRecords join Rooms on KeyCode=Value and Field='KeyCode';  All they keys in KeyRecords, matched to a room row in Rooms.

-->