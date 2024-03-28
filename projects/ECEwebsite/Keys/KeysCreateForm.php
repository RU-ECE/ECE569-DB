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
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit key.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You must be a staff member to create or edit a key using this page.</DIV>";
		goto CloseSQL;
    }
 
    $KeyID = 0;
	if (isset($_GET["keyid"]))
       	if (!ValidateField("keyid", "0123456789-", 10))
	        $KeyID = $_GET["keyid"];

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


    //Initialize all the form data variables. These will be filled in later if an KeyID is present in the query string.
    // If they are not initialized here, PHP spits out many warnings when error reporting is enabled.
    $CreateTime = "";
    $Status = "S";
	$UserID = "";
	$RoomNumber = "";
	$KeyCode = "";
	$KeyNumber = "";
	$NetID = "";
	$FirstName = "";
	$LastName = "";
    $DepartureDateUTC = "";


	//Consolidated "Back to previous search" handling.
    $StatusCheck = "";
    if (isset($_GET["statuscheck"]))
       	if (!ValidateField("statuscheck", "checked", 7))
	        $StatusCheck = $_GET["statuscheck"];

    $StatusSearch = "";
    if (isset($_GET["statussearch"]))
       	if (!ValidateField("statussearch", "LMDRUX", 5))
	        $StatusSearch = $_GET["statussearch"];

    $NetIDCheck = "";
    if (isset($_GET["netidcheck"]))
       	if (!ValidateField("netidcheck", "checked", 7))
	        $NetIDCheck = $_GET["netidcheck"];

    $NetIDSearch = "";
    if (isset($_GET["netidsearch"]))
       	if (!ValidateField("netidsearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 20))
	        $NetIDSearch = $_GET["netidsearch"];

    $NameCheck = "";
    if (isset($_GET["namecheck"]))
       	if (!ValidateField("namecheck", "checked", 7))
	        $NameCheck = $_GET["namecheck"];

    $NameSearch = "";
    if (isset($_GET["namesearch"]))
       	if (!ValidateField("namesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-' ", 30))
	        $NameSearch = $_GET["namesearch"];

    $RoomNumberCheck = "";
    if (isset($_GET["roomnumbercheck"]))
       	if (!ValidateField("roomnumbercheck", "checked", 7))
	        $RoomNumberCheck = $_GET["roomnumbercheck"];

    $RoomNumberSearch = "";
    if (isset($_GET["roomnumbersearch"]))
       	if (!ValidateField("roomnumbersearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 10))
	        $RoomNumberSearch = $_GET["roomnumbersearch"];

    $KeyCodeCheck = "";
    if (isset($_GET["keycodecheck"]))
       	if (!ValidateField("keycodecheck", "checked", 7))
	        $KeyCodeCheck = $_GET["keycodecheck"];

    $KeyCodeSearch = "";
    if (isset($_GET["keycodesearch"]))
       	if (!ValidateField("keycodesearch", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 10))
	        $KeyCodeSearch = $_GET["keycodesearch"];

    $Sort1 = "";
    if (isset($_GET["sort1"]))
       	if (!ValidateField("sort1", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 50))
		    $Sort1 = $_GET["sort1"];

    $Sort2 = "";
    if (isset($_GET["sort2"]))
       	if (!ValidateField("sort2", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 50))
		    $Sort2 = $_GET["sort2"];


    //If the KeyID is specified, get all the fields for this key. With the change record table, the best approach at the moment
    // is to do a separate query for every field, sorted by timestamp so we just get the most recent field value.
    //If there is a query string, fetch key data from the table. Key ID was already pre-initialized to 0.
    if ($KeyID != 0) {

		$TempKeyID = abs($KeyID);

        $Status = RetrieveField($TempKeyID, "Status");
        $RoomNumber = RetrieveField($TempKeyID, "RoomNumber");
    	$KeyCode = RetrieveField($TempKeyID, "KeyCode");
    	$KeyNumber = RetrieveField($TempKeyID, "KeyNumber");
    	$UserID = RetrieveField($TempKeyID, "UserID");

        //We need the name and NetID of the user that has borrowed the key.
        if ($UserID != "") {

		    $SQLQuery = "SELECT
			    UID,
			    (SELECT Value FROM UserRecords WHERE ID='".$UserID."' AND Field='FirstName' ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
			    (SELECT Value FROM UserRecords WHERE ID='".$UserID."' AND Field='LastName' ORDER BY CreateTime DESC LIMIT 1) AS LastName,
			    (SELECT Value FROM UserRecords WHERE ID='".$UserID."' AND Field='NetID' ORDER BY CreateTime DESC LIMIT 1) AS NetID,
			    (SELECT Value FROM UserRecords WHERE ID='".$UserID."' AND Field='DepartureDateUTC' ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC
			    FROM Users WHERE UID='".$UserID."';";
	        $SQLResult = $mysqli->query($SQLQuery);
	        if ($mysqli->error) {
		        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		    echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve name of key borrower: ".$mysqli->error."</DIV>";
		        goto CloseSQL;
	        }

            //Make sure only one user was returned.
	        if ($SQLResult->num_rows != 1) {
		        WriteLog("Order ID ".$UserID." not found in Users table.");
    		    echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">The key borrower could not be found.</DIV>";
		        goto CloseSQL;
            }

            //Save the fields.
            $Fields = $SQLResult->fetch_row();
		    $FirstName = $Fields[1];
		    $LastName = $Fields[2];
		    $NetID = $Fields[3];
            $DepartureDateUTC = $Fields[4];
        }

		//Treat a duplicate key as a new key going forward.
		if ($KeyID < 0)
			$KeyID = 0;
    }

    //We need a list of all the rooms for the dropdown selection.
    $SQLQuery = "SELECT RoomNumber, RoomName, KeyCode FROM Rooms ORDER BY RoomNumber;";
    $SQLResult = $mysqli->query($SQLQuery);
    if ($mysqli->error) {
        WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to construct room list:".$mysqli->error."</DIV>";
        goto CloseSQL;
    }

    //Add each room to the associative array.
    while($Fields = $SQLResult->fetch_row() )
		$RoomNumberList[$Fields[0]] = $Fields[1]." (".$Fields[2].")";


    //Send back the key page..
    echo "<FORM METHOD=\"POST\" ACTION=\"KeysProcessForm.php\">";

	//The create/update button.
	echo "<P>";
    if ($KeyID != 0) {
	    echo "<INPUT TYPE=\"SUBMIT\" VALUE=\"Update Key\">";
		echo " Back to <A HREF=\"KeysCreateList.php?&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&roomnumbercheck=".$RoomNumberCheck."&roomnumbersearch=".urlencode($RoomNumberSearch)."&keycodecheck=".$KeyCodeCheck."&keycodesearch=".urlencode($KeyCodeSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">previous search</A>.";
    }
	else
	    echo "<INPUT TYPE=\"SUBMIT\" VALUE=\"Create Key\">";
	echo "</P>";

    echo "<P>";
	echo "<STRONG>Status: </STRONG><BR>"; DropdownListBox("status", $StatusList, $Status); echo "<BR>\r\n";
    echo "<STRONG>Room:</STRONG><BR>"; DropdownListBox("roomnumber", $RoomNumberList, $RoomNumber); echo "<BR>\r\n";
    echo "<STRONG>Key Code </STRONG>(like BEB25, no spaces):<BR><INPUT TYPE=\"text\" NAME=\"keycode\" VALUE=\"".$KeyCode."\" SIZE=\"10\" MAXLENGTH=\"10\"><BR>\r\n";
    echo "<STRONG>Key Number:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"keynumber\" VALUE=\"".$KeyNumber."\" SIZE=\"4\"  MAXLENGTH=\"4\"><BR>\r\n";
    echo "<STRONG>Loaned To NetID:</STRONG><BR><INPUT TYPE=\"text\" NAME=\"netid\" VALUE=\"".$NetID."\" SIZE=\"20\" MAXLENGTH=\"20\"><BR>\r\n";
	if ($NetID != "") {
		if ($DepartureDateUTC == 0)
		    echo "<STRONG>Name:</STRONG><BR>".$FirstName." ".$LastName.", <STRONG>No Departure Date</STRONG><BR>\r\n";
        else if ($DepartureDateUTC < ($CurrentTime + 2592000))
		    echo "<STRONG>Name:</STRONG><BR>".$FirstName." ".$LastName.", <STRONG>****DEPARTURE DATE PASSED**** ".date("F Y", $DepartureDateUTC)."</STRONG><BR>\r\n";
        else
		    echo "<STRONG>Name:</STRONG><BR>".$FirstName." ".$LastName.", Departure Date: ".date("F Y", $DepartureDateUTC)."<BR>\r\n";
    }
    echo "</P>";


	//Embed all the search fields so we can make a "return to search page" after the update is processed.
	// 8/14/2023 Removed test for KeyID=0. Otherwise the search fields won't be embedded in the form when duplicating a key.
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statuscheck\"      VALUE=\"".$StatusCheck."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"statussearch\"     VALUE=\"".$StatusSearch."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidcheck\"       VALUE=\"".$NetIDCheck."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"netidsearch\"      VALUE=\"".$NetIDSearch."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"namecheck\"        VALUE=\"".$NameCheck."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"namesearch\"       VALUE=\"".$NameSearch."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"roomnumbercheck\"  VALUE=\"".$RoomNumberCheck."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"roomnumbersearch\" VALUE=\"".$RoomNumberSearch."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"keycodecheck\"     VALUE=\"".$KeyCodeCheck."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"keycodesearch\"    VALUE=\"".$KeyCodeSearch."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort1\"            VALUE=\"".$Sort1."\">\r\n";
	echo "<INPUT TYPE=\"HIDDEN\" NAME=\"sort2\"            VALUE=\"".$Sort2."\">\r\n";

    //Notes are just another row in the change record table.
    if ($KeyID != 0) {

        //Get all the notes for this key, sorted by timestamp.
        $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM KeyRecords AS T1 WHERE ID=".$KeyID." ORDER BY CreateTime DESC;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." looking up key notes ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing notes: ".$mysqli->error."</DIV>";
            goto CloseForm;
	    }

	    //Display the user query results in a table if any rows were found.
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
                    echo " Key Status changed to ".$StatusList[$Fields[3]]."\r\n";
                else
                    echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		    }
		    echo "</TEXTAREA></P>";
	    }
    }

    //Additional notes.
    echo "<P><STRONG>New Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";


    //The create/update button.
    if ($KeyID != 0) {

        //If this is an existing key store the Key ID in a hidden field.
	    echo "<INPUT TYPE=\"HIDDEN\" NAME=\"keyid\" VALUE=\"".$KeyID."\">\r\n";
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update Key\">";
		// 8/14/2023 Added all the search fields so the previous search link will work when duplicating a key.
		echo " Create <A HREF=\"KeysCreateForm.php?keyid=".-$KeyID."&statuscheck=".$StatusCheck."&statussearch=".$StatusSearch."&netidcheck=".$NetIDCheck."&netidsearch=".urlencode($NetIDSearch)."&namecheck=".$NameCheck."&namesearch=".urlencode($NameSearch)."&roomnumbercheck=".$RoomNumberCheck."&roomnumbersearch=".urlencode($RoomNumberSearch)."&keycodecheck=".$KeyCodeCheck."&keycodesearch=".urlencode($KeyCodeSearch)."&sort1=".urlencode($Sort1)."&sort2=".urlencode($Sort2)."\">duplicate</A>.</P>";
    }
    else
	    echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Key\"></P>";


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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Keys/KeysCreateForm.log"); 
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
    function RetrieveField($KID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM KeyRecords WHERE ID=".$KID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
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

