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

    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";


    //Access checks.
    // Only staff is allowed to view/change equipment.
    if ( ($UserDoingUpdateAccessRole != "S") && ($UserDoingUpdateAccessRole != "F") ) {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to edit EquipmentID ".$EquipmentID);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have permission to edit this equipment.</DIV>";
		goto CloseSQL;
    }
 
    //Array of names for equipment status dropdown list box
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


    //Initialize all the form data variables. These will be filled in later if an EquipmentID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.
    $Status = "";
    $ECETagNumber = "";
    $RUTagNumber = "";
    $Manufacturer = "";
    $ModelNumber = "";
    $SerialNumber = "";
    $Type = "";
    $Description = "";
    $AcquireDate = "";
    $Cost = 0;
    $Location = "";
    $PONumber = "";
    $Project = "";
    $Location = "";
    $OwnerID = "";
    $OwnerFirstName = "";
    $OwnerLastName = "";
    $OwnerNetID = "";

    $EquipmentID = 0;
    if (isset($_GET["equipid"]))
       	if (!ValidateField("equipid", "0123456789-", 5))
    	    $EquipmentID = $_GET["equipid"];


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
		    $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
	if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "ALSBMRHPDTU", 5))
		    $StatusSearch = $_GET["statussearch"];

    $TagCheck = "";
    if (isset($_GET["tagcheck"]))
       	if (!ValidateField("tagcheck", "checked", 7))
		    $TagCheck = $_GET["tagcheck"];

    $ECETagSearch = "";
    if (isset($_GET["ecetagsearch"]))
       	if (!ValidateField("ecetagsearch", "0123456789", 10))
   		    $ECETagSearch = $_GET["ecetagsearch"];

    $RUTagSearch = "";
    if (isset($_GET["rutagsearch"]))
       	if (!ValidateField("rutagsearch", "0123456789", 10))
		    $RUTagSearch = $_GET["rutagsearch"];

	$NetIDCheck = "";
    if (isset($_GET["netidcheck"]))
       	if (!ValidateField("netidcheck", "checked", 7))
		    $NetIDCheck = $_GET["netidcheck"];

 	$NetIDSearch = "";
    if (isset($_GET["netidsearch"]))
        if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyz0123456789", 50))
            $NetIDSearch = $_GET["netidsearch"];

    $TypeCheck = "";
    if (isset($_GET["typecheck"]))
       	if (!ValidateField("typecheck", "checked", 7))
		    $TypeCheck = $_GET["typecheck"];

    $TypeSearch = "";
    if (isset($_GET["typesearch"]))
        if (!ValidateField("typesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-", 50))
            $TypeSearch = $_GET["typesearch"];

    $MakeCheck = "";
    if (isset($_GET["makecheck"]))
       	if (!ValidateField("makecheck", "checked", 7))
		    $MakeCheck = $_GET["makecheck"];

    $MakeSearch = "";
    if (isset($_GET["makesearch"]))
       	if (!ValidateField("makesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 20))
   		    $MakeSearch = $_GET["makesearch"];

    $LocationCheck = "";
    if (isset($_GET["locationcheck"]))
       	if (!ValidateField("locationcheck", "checked", 7))
		    $LocationCheck = $_GET["locationcheck"];

    $LocationSearch = "";
	if (isset($_GET["locationsearch"]))
       	if (!ValidateField("locationsearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789()- ", 20))
   		    $LocationSearch = $_GET["locationsearch"];

    $Sort1 = "";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789() ", 50))
		    $Sort2 = $_GET["sort2"];


    //If the EquipmentID is specified, get all the fields for this equipment. With the change record table, the best approach at the moment
    // is to do a separate query for every field, sorted by timestamp so we just get the most recent field value.
    if ($EquipmentID != 0) {	

		//If the user wants to create a copy of this equipment, the EquipmentID will be negative.
		$EID = abs($EquipmentID);

        $Status = RetrieveField($EID, "Status");
        $ECETagNumber = RetrieveField($EID, "ECETag");
        $RUTagNumber = RetrieveField($EID, "RUTag");
        $Manufacturer = RetrieveField($EID, "Make");
        $ModelNumber = RetrieveField($EID, "Model");
        $SerialNumber = RetrieveField($EID, "Serial");
        $Type = RetrieveField($EID, "Type");
        $Description = RetrieveField($EID, "Description");
        $Cost = intval(RetrieveField($EID, "Cost"));
        $PONumber = RetrieveField($EID, "PONumber");
        $Project = RetrieveField($EID, "Project");
        $Location = RetrieveField($EID, "Location");
        $OwnerID = RetrieveField($EID, "OwnerID");

        if ( ($AcquireDateUTC = RetrieveField($EID, "AcquireDateUTC")) != "")
			$AcquireDate = date("m/d/Y", (int)$AcquireDateUTC);

		if ($EquipmentID < 0)
			$EquipmentID = 0;
    }


    //Lookup the netID and name of the person who may have borrowed this item.
    if ($OwnerID != "") {

        $SQLQuery = "
                SELECT UID,
                    (SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=".$OwnerID." ORDER BY CreateTime DESC LIMIT 1) AS NetID,
                    (SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=".$OwnerID." ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
                    (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=".$OwnerID." ORDER BY CreateTime DESC LIMIT 1) AS LastName
                FROM Users WHERE UID=".$OwnerID.";";
        $SQLResult = $mysqli->query($SQLQuery);
        if ($mysqli->error) {
            WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		    echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get owner information:".$mysqli->error."</DIV>";
            goto CloseSQL;
        }

        //Grab the owner name and NetID.
        $Fields = $SQLResult->fetch_row();
        $OwnerNetID = $Fields[1];
        $OwnerFirstName = $Fields[2];
        $OwnerLastName = $Fields[3];
    }


    echo "<FORM METHOD=\"POST\" ACTION=\"EquipmentProcessForm.php\">";

    //The create/update button.
    echo "<P>";
    if ($EquipmentID != 0) {
	    echo "<INPUT TYPE=\"SUBMIT\" VALUE=\"Update Equipment\">";
	    echo " Back to <A HREF=\"EquipmentCreateList.php?statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&tagcheck=".$TagCheck."&ecetagsearch=".urlencode($ECETagSearch)."&rutagsearch=".urlencode($RUTagSearch)."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&typecheck=".$TypeCheck."&typesearch=".urlencode($TypeSearch)."&makecheck=".$MakeCheck."&makesearch=".urlencode($MakeSearch)."&locationcheck=".$LocationCheck."&locationsearch=".urlencode($LocationSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.";
		echo " Create <A HREF=\"EquipmentCreateForm.php?equipid=".-$EquipmentID."\">duplicate</A>.";
    }
	else
	    printf("<INPUT TYPE=\"SUBMIT\" VALUE=\"Create Equipment\">");
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Equipment Status</STRONG><BR>"; DropdownListBox("status", $StatusList, $Status); echo "<BR>\r\n";
    echo "<STRONG>ECE Tag Number</STRONG>  <BR>\r\n<INPUT TYPE=\"text\" NAME=\"ecetag\" VALUE=\"".$ECETagNumber."\" SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>RU Tag Number</STRONG> (Put all tag numbers if more than one. For Example: 0020178432/352023)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"rutag\" VALUE=\"".$RUTagNumber."\" SIZE=\"20\" MAXLENGTH=\"20\"><BR>\r\n";
    echo "<HR>";
    echo "<STRONG>Manufacturer</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"make\" VALUE=\"".$Manufacturer."\" SIZE=\"50\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Model Number</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"model\" VALUE=\"".$ModelNumber."\"  SIZE=\"50\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Type</STRONG> (For Example: Oscilloscope, Power Supply, Function Generator, Multimeter, Computer-Laptop, Computer-Desktop, Computer-Server)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"type\" VALUE=\"".$Type."\" SIZE=\"20\" MAXLENGTH=\"40\"><BR>\r\n";
    echo "<STRONG>Description</STRONG>(More details, if needed)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"description\"  VALUE=\"".$Description."\"  SIZE=\"50\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Serial Number</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"serial\" VALUE=\"".$SerialNumber."\" SIZE=\"20\" MAXLENGTH=\"30\"><BR>\r\n";
    echo "<HR>";
    echo "<STRONG>Acquired Date (Month/Day/Year)</STRONG><BR><INPUT TYPE=\"text\" NAME=\"acquiredate\" VALUE=\"".$AcquireDate."\" SIZE=\"10\"  MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Cost</STRONG><BR>\r\n$<INPUT TYPE=\"text\" NAME=\"cost\" VALUE=\"".number_format($Cost / 100, 2, ".", "")."\" SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>PO Number</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"ponumber\" VALUE=\"".$PONumber."\" SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Project/GL</STRONG>(Fill in only for grant funded projects)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"project\" VALUE=\"".$Project."\" SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Location</STRONG> (For Example: ECE109, CORE531, C206)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"location\" VALUE=\"".$Location."\" SIZE=\"20\" MAXLENGTH=\"20\"><BR>\r\n";
    echo "<STRONG>NetID</STRONG> (if loaned, or in possession of a faculty)<BR>\r\n<INPUT TYPE=\"text\" NAME=\"ownernetid\" VALUE=\"".$OwnerNetID."\" SIZE=\"20\" MAXLENGTH=\"20\"> ".$OwnerFirstName." ".$OwnerLastName."<BR>\r\n";
    echo "</P>";

	//Embed all the search fields so we can make a "return to search page" after the update is processed.
    if ($EquipmentID != 0) {
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"    VALUE=\"".$StatusCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"   VALUE=\"".$StatusSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"tagcheck\"       VALUE=\"".$TagCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"ecetagsearch\"   VALUE=\"".$ECETagSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"rutagsearch\"    VALUE=\"".$RUTagSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidcheck\"     VALUE=\"".$NetIDCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidsearch\"    VALUE=\"".$NetIDSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"typecheck\"      VALUE=\"".$TypeCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"typesearch\"     VALUE=\"".$TypeSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"makecheck\"      VALUE=\"".$MakeCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"makesearch\"     VALUE=\"".$MakeSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"locationcheck\"  VALUE=\"".$LocationCheck."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"locationsearch\" VALUE=\"".$LocationSearch."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"          VALUE=\"".$Sort1."\">\r\n";
		echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"          VALUE=\"".$Sort2."\">\r\n";
	}

    //Display the existing notes and change records. Notes are just another row in the change record table.
    if ($EquipmentID != 0) {

        //Get all the change records for this equipment, sorted by timestamp.
        $SQLQuery = "
            SELECT T1.CreateUID, 
            T1.CreateTime, 
            T1.Field, 
            T1.Value, 
            (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) 
            FROM EquipRecords AS T1 
            WHERE ID=".$EquipmentID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up change records ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing change records: ".$mysqli->error."</DIV>";
            goto CloseForm;
	    }

	    //Display the equipment query results if any rows were found.
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
                    echo " Equipment Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else if ($Fields[2] == "Cost")
                    echo " Cost changed to $".number_format($Fields[3] / 100, 2, ".", ",")."\r\n";
                else if ( ($Fields[2] == "AcquireDateUTC") && ($Fields[3] != "") )
                    echo " Acquire Date changed to ".date("F j, Y, g:i a", $Fields[3])."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>Additional Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"10\" COLS=\"80\"></TEXTAREA></P>";

    //The create/update button.
    if ($EquipmentID != 0) {
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"equipid\" VALUE=\"".$EquipmentID."\">\r\n";
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Equipment\"></P>";
    }
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Equipment\"></P>";

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Equipment/EquipmentCreateForm.log"); 
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


    //Function to retrieve a specified field for a specified piece of equipment.
    function RetrieveField($UID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM EquipRecords WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve equipment field from database: ".$mysqli->error."</DIV>";
		    return "";
	    }

        //Not all fields are defined for every piece of equipment, so it is not an error for the field to not be found.
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