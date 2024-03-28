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
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit Equipment.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
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
      "H"=>"Ready for Pickup",
      "P"=>"Surplused",
      "D"=>"Discarded",
      "T"=>"Returned",
      "U"=>"Unknown"
      );

    $SortList = array
      (
      ""=>"",
      "CAST(ECETag AS unsigned)"=>"ECE Tag Number",
      "CAST(RUTag AS unsigned)"=>"Rutgers Tag Number",
      "Location"=>"Location",
	  "DepartureDateUTC"=>"Graduation Date"
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
    $Sort1 = "CAST(ECETag AS unsigned)";
    $Sort2 = "";

    //All the form fields that can end up in the SQL query are checked, and blanked out if there are errors.

    //Setup the default button states based on form fields 
    if (isset($_GET["equipid"]))
       	if (!ValidateField("equipid", "0123456789", 5))
	        $EquipID = $_GET["equipid"];

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


    //Print the links to the other sortings.
    echo "<FORM METHOD=\"GET\" ACTION=\"EquipmentCreateList.php\">\r\n";
    echo "<TABLE CLASS=\"table table-borderless table-sm\"><TBODY>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"statuscheck\" ".$StatusCheck.">Show only equipment of "; DropdownListBox("statussearch", $StatusList, $StatusSearch); echo " status.</TD>\r\n";
    echo "<TD><INPUT TYPE=\"SUBMIT\" VALUE=\"Update List\"></FORM></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"tagcheck\" ".$TagCheck.">Show only equipment with ECE Tag number <INPUT TYPE=\"text\" NAME=\"ecetagsearch\" VALUE=\"".$ECETagSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"> or RU Tag number <INPUT TYPE=\"text\" NAME=\"rutagsearch\" VALUE=\"".$RUTagSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"netidcheck\" ".$NetIDCheck.">Show only equipment loaned to <INPUT TYPE=\"text\" NAME=\"netidsearch\" VALUE=\"".$NetIDSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"typecheck\" ".$TypeCheck.">Show only equipment type <INPUT TYPE=\"text\" NAME=\"typesearch\" VALUE=\"".$TypeSearch."\" SIZE=\"10\" MAXLENGTH=\"30\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"makecheck\" ".$MakeCheck.">Show only equipment made by <INPUT TYPE=\"text\" NAME=\"makesearch\" VALUE=\"".$MakeSearch."\" SIZE=\"10\" MAXLENGTH=\"30\"></TD></TR>\r\n";
    echo "<TR><TD><INPUT TYPE=\"checkbox\" VALUE=\"checked\" NAME=\"locationcheck\" ".$LocationCheck.">Show only equipment located in <INPUT TYPE=\"text\" NAME=\"locationsearch\" VALUE=\"".$LocationSearch."\" SIZE=\"10\" MAXLENGTH=\"20\"></TD></TR>\r\n";
    echo "<TR><TD>Sort first by "; DropdownListBox("sort1", $SortList, $Sort1); echo "and then by "; DropdownListBox("sort2", $SortList, $Sort2); echo "</TD></TR>\r\n";
    echo "</TBODY></TABLE>\r\n";

	//Check if this is a search for a specific request, and if the ID is zero.
	if ($EquipID == "0") {
        echo "0 items found.<BR>";
		goto CloseSQL;
	}

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
    $SQLQuery = "SELECT Equip.EID, Equipment.ECETag, Equipment.RUTag, Equipment.Status, Equipment.Make, Equipment.Model, Equipment.Type, Equipment.Location, Equipment.Serial, Equipment.OwnerID, User.FirstName, User.LastName, User.NetID, User.DepartureDateUTC FROM Equip
        LEFT JOIN
            (SELECT EID,
                (SELECT Value FROM EquipRecords WHERE Field='ECETag' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS ECETag,
                (SELECT Value FROM EquipRecords WHERE Field='RUTag' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS RUTag,
                (SELECT Value FROM EquipRecords WHERE Field='Status' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Status,
                (SELECT Value FROM EquipRecords WHERE Field='Make' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Make,
                (SELECT Value FROM EquipRecords WHERE Field='Model' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Model,
                (SELECT Value FROM EquipRecords WHERE Field='Type' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Type,
                (SELECT Value FROM EquipRecords WHERE Field='Location' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Location,
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
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get equipment list: ".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Display the user query results in a table if any rows were found.
    if ($SQLResult->num_rows > 0) {

        echo $SQLResult->num_rows." items found. <A HREF=\"/Equipment/EquipmentCreateExport.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&tagcheck=".$TagCheck."&ecetagsearch=".urlencode($ECETagSearch)."&rutagsearch=".urlencode($RUTagSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&typecheck=".$TypeCheck."&typesearch=".urlencode($TypeSearch)."&makecheck=".$MakeCheck."&makesearch=".urlencode($MakeSearch)."&locationcheck=".$LocationCheck."&locationsearch=".urlencode($LocationSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">Export</A> this list.<BR>";
        echo "<TABLE CLASS=\"table table-striped table-bordered\">";
        echo "<THEAD CLASS=\"thead-dark\">";
        echo "<TR><TH>ECE Tag</TH><TH>RU Tag</TH><TH>Status</TH><TH>Manufacturer</TH><TH>Model</TH><TH>Type</TH><TH>Location</TH><TH>Name</TH><TH>NetID</TH><TH>Departure</TH></TR>";
        echo "</THEAD>";
        while($Fields = $SQLResult->fetch_assoc()) {

            echo "<TR>";
            //ECE Tag is not present on all equipment, but still print something in the table so use can click on it.
            if ($Fields["ECETag"] == "")
                $ECETag = "None";
            else
                $ECETag = $Fields["ECETag"];
            echo "<TD><A HREF=\"EquipmentCreateForm.php?equipid=".$Fields["EID"]."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&tagcheck=".$TagCheck."&ecetagsearch=".urlencode($ECETagSearch)."&rutagsearch=".urlencode($RUTagSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&typecheck=".$TypeCheck."&typesearch=".urlencode($TypeSearch)."&makecheck=".$MakeCheck."&makesearch=".urlencode($MakeSearch)."&locationcheck=".$LocationCheck."&locationsearch=".urlencode($LocationSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">".$ECETag."</A></TD>";
            echo "<TD>".$Fields["RUTag"]."</TD>";
            echo "<TD>".$StatusList[$Fields["Status"]]."</TD>";
            echo "<TD>".$Fields["Make"]."</TD>";
            echo "<TD>".$Fields["Model"]."</TD>";
            echo "<TD>".$Fields["Type"]."</TD>";
            echo "<TD>".$Fields["Location"]."</TD>";

            //If the equipment is loaned out, print who has it and their expected graduation date.
            if ($Fields["Status"] == "L") {
                echo "<TD>".$Fields["FirstName"]." ".$Fields["LastName"]."</TD>\n\r";
                echo "<TD><A HREF=\"../Users/UsersCreateForm.php?userid=".$Fields["OwnerID"]."\">".$Fields["NetID"]."</A></TD>\r\n";
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

            echo "</TR>\r\n";
        }
        echo "</TABLE>";
        echo $SQLResult->num_rows." items found.";
    }
    else 
        echo "0 items found.";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Equipment/EquipmentCreateList.log"); 
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

<!--
CREATE TABLE `Equip` (
  `EID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT
);

CREATE TABLE `EquipRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

-->
