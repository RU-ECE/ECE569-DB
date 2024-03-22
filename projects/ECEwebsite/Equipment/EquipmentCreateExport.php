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
    $Title = "Equipment";
    $Menu = "Equipment";
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
    $UserDoingUpdateNetID = $_SESSION['netid'];

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
    $SQLQuery = "SELECT ID, Value FROM UserRecords WHERE Field='AccessRole' AND ID=(SELECT ID FROM UserRecords WHERE Field='NetID' AND Value='".$UserDoingUpdateNetID."' ORDER BY CreateTime DESC LIMIT 1) ORDER BY CreateTime DESC LIMIT 1;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    	require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Database query for user ".$UserDoingUpdateNetID." has failed: ".$mysqli->error."</DIV>";
        require('../template/footer.html');
        require('../template/foot.php');
		goto CloseSQL;
	}

	//Check if user was found.
	if ($SQLResult->num_rows < 1) {
   		WriteLog("User with NetID ".$UserDoingUpdateNetID." not found.");
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">User ".$UserDoingUpdateNetID." not found.</DIV>";
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
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit EquipmentID ".$EquipmentID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
        require('../template/footer.html');
        require('../template/foot.php');
		goto CloseSQL;
    }

    //Array of names for user status dropdown list box
    //ALSBMRPDTU
    $StatusList = array
      (
      ""=>"",
      "A"=>"Active",
      "L"=>"Loaned",
      "S"=>"Stored",
      "B"=>"Broken",
      "M"=>"Missing",
      "R"=>"Ready to Surplus",
      "P"=>"Surplused",
      "D"=>"Discarded",
      "T"=>"Returned",
      "U"=>"Unknown"
      );


    //This had to be done to get rid of all the undefined variable warnings.
	$EquipID = "";
    $StatusCheck = "";
    $StatusSearch = "A";
	$NetIDCheck = "";
	$NetIDSearch = "";
    $TagCheck = "";
    $ECETagSearch = "";
    $RUTagSearch = "";
    $TypeCheck = "";
    $TypeSearch = "";
    $MakeCheck = "";
    $MakeSearch = "";
    $LocationCheck = "";
    $LocationSearch = "";
    $Sort1 = "";
    $Sort2 = "";

    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.
    //Setup the default button states based on form fields 
    if (isset($_GET["statuscheck"]))
	    if ($_GET["statuscheck"] != "")
		    $StatusCheck = $_GET["statuscheck"];

    if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "ALSBMRPDTU", 5))
		    $StatusSearch = $_GET["statussearch"];

    if (isset($_GET["tagcheck"]))
	    if ($_GET["tagcheck"] != "")
		    $TagCheck = $_GET["tagcheck"];

    if (isset($_GET["ecetagsearch"]))
       	if (!ValidateField("ecetagsearch", "0123456789", 10))
   		    $ECETagSearch = $_GET["ecetagsearch"];

    if (isset($_GET["rutagsearch"]))
       	if (!ValidateField("rutagsearch", "0123456789", 10))
		    $RUTagSearch = $_GET["rutagsearch"];

    if (isset($_GET["netidcheck"]))
	    if ($_GET["netidcheck"] != "")
		    $NetIDCheck = $_GET["netidcheck"];

    if (isset($_GET["netidsearch"]))
        if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyz0123456789", 50))
            $NetIDSearch = $_GET["netidsearch"];

    if (isset($_GET["typecheck"]))
	    if ($_GET["typecheck"] != "")
		    $TypeCheck = $_GET["typecheck"];

    if (isset($_GET["typesearch"]))
        if (!ValidateField("typesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-", 50))
            $TypeSearch = $_GET["typesearch"];

    if (isset($_GET["makecheck"]))
	    if ($_GET["makecheck"] != "")
		    $MakeCheck = $_GET["makecheck"];

    if (isset($_GET["makesearch"]))
       	if (!ValidateField("makesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 20))
   		    $MakeSearch = $_GET["makesearch"];

    if (isset($_GET["locationcheck"]))
	    if ($_GET["locationcheck"] != "")
		    $LocationCheck = $_GET["locationcheck"];

    if (isset($_GET["locationsearch"]))
       	if (!ValidateField("locationsearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789()- ", 20))
   		    $LocationSearch = $_GET["locationsearch"];

    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //Compose the SQL query string based on the user's selections. All the conditions have to be ANDed togehter.
    $Conditions = "";
    $Sorting = "";

    //Compose the conditions string. All the fields that can end up directly in the query string need to be sponged.
    if ($TagCheck && $ECETagSearch)
	    $Conditions .= "AND (ECETag='".mysqli_real_escape_string($mysqli, $ECETagSearch)."') ";
    if ($TagCheck && $RUTagSearch)
	    $Conditions .= "AND (RUTag LIKE '%".mysqli_real_escape_string($mysqli, $RUTagSearch)."%') ";
    if ($NetIDCheck && $NetIDSearch)
	    $Conditions .= "AND (NetID='".mysqli_real_escape_string($mysqli, $NetIDSearch)."') ";
    if ($TypeCheck && $TypeSearch)
	    $Conditions .= "AND (Type LIKE '%".mysqli_real_escape_string($mysqli, $TypeSearch)."%') ";
    if ($MakeCheck && $MakeSearch)
	    $Conditions .= "AND (Make LIKE '%".mysqli_real_escape_string($mysqli, $MakeSearch)."%') ";
    if ($StatusCheck && $StatusSearch)
	    $Conditions .= "AND (Status='".mysqli_real_escape_string($mysqli, $StatusSearch)."') ";
    if ($LocationCheck && $LocationSearch)
	    $Conditions .= "AND (Location LIKE '%".mysqli_real_escape_string($mysqli, $LocationSearch)."%') ";

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
    $SQLQuery = "SELECT Equip.EID, Equipment.ECETag, Equipment.RUTag, Equipment.Status, Equipment.Make, Equipment.Model, Equipment.Type, Equipment.AcquireDateUTC, Equipment.Cost, Equipment.Location, Equipment.PONumber, Equipment.Project, Equipment.Serial, Equipment.OwnerID, User.FirstName, User.LastName, User.NetID, User.DepartureDateUTC FROM Equip
        LEFT JOIN
            (SELECT EID,
                (SELECT Value FROM EquipRecords WHERE Field='ECETag' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS ECETag,
                (SELECT Value FROM EquipRecords WHERE Field='RUTag' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS RUTag,
                (SELECT Value FROM EquipRecords WHERE Field='Status' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Status,
                (SELECT Value FROM EquipRecords WHERE Field='Make' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Make,
                (SELECT Value FROM EquipRecords WHERE Field='Model' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Model,
                (SELECT Value FROM EquipRecords WHERE Field='Type' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Type,
                (SELECT Value FROM EquipRecords WHERE Field='AcquireDateUTC' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS AcquireDateUTC,
                (SELECT Value FROM EquipRecords WHERE Field='Cost' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Cost,
                (SELECT Value FROM EquipRecords WHERE Field='Location' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Location,
                (SELECT Value FROM EquipRecords WHERE Field='PONumber' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS PONumber,
                (SELECT Value FROM EquipRecords WHERE Field='Project' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Project,
                (SELECT Value FROM EquipRecords WHERE Field='Serial' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Serial,
                (SELECT Value FROM EquipRecords WHERE Field='OwnerID' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS OwnerID
            FROM Equip) AS Equipment
        ON Equip.EID=Equipment.EID
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC
			FROM Users) AS User
		ON User.UID=Equipment.OwnerID
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
    header('Content-Disposition: attachment; filename="EquipmentExport.csv"');

    //Display the user query results in a table if any rows were found.
    if ($SQLResult->num_rows > 0) {

        //CSV File header.
        echo "ECE Tag,RU Tag,Status,Manufacturer,Model,Type,Serial,Acquired,Cost,PO,Project,Location,Name,NetID,DepartureDate\r\n";

        while($Fields = $SQLResult->fetch_assoc()) {

            echo "\"".$Fields["ECETag"]."\",";
            echo "\"".$Fields["RUTag"]."\",";
            echo $StatusList[$Fields["Status"]].",";
            echo "\"".$Fields["Make"]."\",";
            echo "\"".$Fields["Model"]."\",";
            echo "\"".$Fields["Type"]."\",";
            echo "\"".$Fields["Serial"]."\",";

            if ($Fields["AcquireDateUTC"] != "")
                echo "\"".date("m/d/Y", $Fields["AcquireDateUTC"])."\",";
            else
                echo ",";

            if ($Fields["Cost"] != "")
                echo "\"$".number_format($Fields["Cost"] / 100, 2, ".", ",")."\",";
            else
                echo ",";

            echo "\"".$Fields["PONumber"]."\",";
            echo "\"".$Fields["Project"]."\",";
            echo "\"".$Fields["Location"]."\",";

            //If the equipment is loaned out, print who has it and their expected graduation date.
            echo "\"".$Fields["FirstName"]." ".$Fields["LastName"]."\",";
            echo "\"".$Fields["NetID"]."\",";
            echo "\"".date("m/d/Y", $Fields["DepartureDateUTC"])."\"\r\n";
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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Equipment/EquipmentCreateExport.log"); 
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
