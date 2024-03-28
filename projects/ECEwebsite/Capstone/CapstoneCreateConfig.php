<?php
    session_start();

	//Error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    //Set the timezone for the date functions.
    date_default_timezone_set('America/New_York');

    //Set for the title of the page.
    $title = "ECE Apps - Capstone Settings";
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

	//Log the start of the script.
	WriteLog("Started. Remote IP ".$_SERVER['REMOTE_ADDR']);

    //Get the configuration data from the Capstone configuration table.
	//The date of the actual expo event.
	$SpringExpoDateUTC = RetrieveField("CapstoneConfig", "0", "SpringExpoDateUTC");
	$FallExpoDateUTC = RetrieveField("CapstoneConfig", "0", "FallExpoDateUTC");
	//The cutoff for when teams should be formed, team numbers will be changed to fill gaps, and the system will no longer allow students to change their teams.
	$SpringFormedDateUTC = RetrieveField("CapstoneConfig", "0", "SpringFormedDateUTC");
	$FallFormedDateUTC = RetrieveField("CapstoneConfig", "0", "FallFormedDateUTC");
	//The start of the Round presentations. Six days later, the results will be emailed to all teams.
	$SpringRound1DateUTC = RetrieveField("CapstoneConfig", "0", "SpringRound1DateUTC");
	$FallRound1DateUTC = RetrieveField("CapstoneConfig", "0", "FallRound1DateUTC");
	$SpringRound2DateUTC = RetrieveField("CapstoneConfig", "0", "SpringRound2DateUTC");
	$FallRound2DateUTC = RetrieveField("CapstoneConfig", "0", "FallRound2DateUTC");
	$SpringRound3DateUTC = RetrieveField("CapstoneConfig", "0", "SpringRound3DateUTC");
	$FallRound3DateUTC = RetrieveField("CapstoneConfig", "0", "FallRound3DateUTC");

	//The UserID of the person whose email should appear in the "Reply-To" field of automated emails.
	$ReplyToEmailUID = RetrieveField("CapstoneConfig", "0", "ReplyToEmailUID");
	$ReplyToNetID = RetrieveField("UserRecords", $ReplyToEmailUID, "NetID");
	$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");

	//The UserID of the person who should receive email when a new order comes in.
	$NewOrderEmailUID = RetrieveField("CapstoneConfig", "0", "NewOrderEmailUID");
	$NewOrderNetID = RetrieveField("UserRecords", $NewOrderEmailUID, "NetID");
	$NewOrderEmail = RetrieveField("UserRecords", $NewOrderEmailUID, "PreferredEmail");

	//The UserID of the person who should receive email when an order has been approved for purchasing.
	$ApprovedOrderEmailUID = RetrieveField("CapstoneConfig", "0", "ApprovedOrderEmailUID");
	$ApprovedOrderNetID = RetrieveField("UserRecords", $ApprovedOrderEmailUID, "NetID");
	$ApprovedOrderEmail = RetrieveField("UserRecords", $ApprovedOrderEmailUID, "PreferredEmail");

	//Stock email messages sent to remind students to return their capstone items.
	$Email1 = RetrieveField("CapstoneConfig", "0", "Email1");
	$Email2 = RetrieveField("CapstoneConfig", "0", "Email2");
	$Email3 = RetrieveField("CapstoneConfig", "0", "Email3");


    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

	echo "<FORM METHOD=\"POST\" ACTION=\"CapstoneProcessConfig.php\">";

	//Submit button.
	echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update\"></P>";

    echo "<P><STRONG>ReplyTo NetID</STRONG><BR>The NetID of the user whose email address should be used in the Reply-To: field of automated emails.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"replytonetid\" MAXLENGTH=\"25\" SIZE=\"25\" VALUE=\"".$ReplyToNetID."\"> ";
	echo $ReplyToEmail."</P>\r\n";

    echo "<P><STRONG>New Order NetID</STRONG><BR>The NetID of the user who should receive an alert email when a new order is placed.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"newordernetid\" MAXLENGTH=\"25\" SIZE=\"25\" VALUE=\"".$NewOrderNetID."\"> ";
	echo $NewOrderEmail."</P>\r\n";

	echo "<P><STRONG>Approved Order NetID</STRONG><BR>The NetID of the user who should receive an alert email when a new order has been approved.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"approvedordernetid\" MAXLENGTH=\"25\" SIZE=\"25\" VALUE=\"".$ApprovedOrderNetID."\"> ";
	echo $ApprovedOrderEmail."</P>\r\n";

	echo "<HR>";
    echo "<H3><STRONG>Spring Capstone Dates</STRONG></H3>";

    echo "<P><STRONG>Expo Date (M/D/Y)</STRONG><BR>Do not change this until at least 11 days after the event.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"springexpodate\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$SpringExpoDateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Teams Due Date (M/D/Y)</STRONG><BR>Date on which teams must be formed, system will re-assign team numbers to fill gaps, and student can no longer change teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"springformeddate\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$SpringFormedDateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Round 1 Presentations (M/D/Y)</STRONG><BR>The Monday on which Round 1 presentations begin. Six days later (Sunday at 3am) the results will be emailed to all teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"springround1date\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$SpringRound1DateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Round 2 Presentations (M/D/Y)</STRONG><BR>The Monday on which Round 2 presentations begin. Six days later (Sunday at 3am) the results will be emailed to all teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"springround2date\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$SpringRound2DateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Round 3 Presentations (M/D/Y)</STRONG><BR>The Monday on which Round 3 presentations begin. Six days later (Sunday at 3am) the results will be emailed to all teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"springround3date\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$SpringRound3DateUTC)."\"></P>\r\n";

	echo "<HR>";
    echo "<H3><STRONG>Fall Capstone Dates</STRONG></H3>";

    echo "<P><STRONG>Expo Date (M/D/Y)</STRONG><BR>Do not change this until at least 11 days after the event.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"fallexpodate\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$FallExpoDateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Teams Due Date (M/D/Y)</STRONG><BR>Date on which teams must be formed, system will re-assign team numbers to fill gaps, and student can no longer change teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"fallformeddate\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$FallFormedDateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Round 1 Presentations (M/D/Y)</STRONG><BR>The Monday on which Round 1 presentations begin. Six days later (Sunday at 3am) the results will be emailed to all teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"fallround1date\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$FallRound1DateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Round 2 Presentations (M/D/Y)</STRONG><BR>The Monday on which Round 2 presentations begin. Six days later (Sunday at 3am) the results will be emailed to all teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"fallround2date\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$FallRound2DateUTC)."\"></P>\r\n";

    echo "<P><STRONG>Round 3 Presentations (M/D/Y)</STRONG><BR>The Monday on which Round 3 presentations begin. Six days later (Sunday at 3am) the results will be emailed to all teams.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"fallround3date\" MAXLENGTH=\"10\" SIZE=\"10\" VALUE=\"".date("m/d/Y", (int)$FallRound3DateUTC)."\"></P>\r\n";

	//First Email notice asking to return items.
	echo "<HR>";
    echo "<H3><STRONG>First Email - 2 Days After Capstone</STRONG></H3>";
	echo "<P>Dear StudentName,<BR>";
	echo "<TEXTAREA NAME=\"email1\" ROWS=\"5\" COLS=\"80\">".$Email1."</TEXTAREA><BR>";
	echo "Sincerely,<BR>ECE SPN System<BR><BR> Quantity 1 Arduino R3 WiFi<BR> Quantity 2 USB-to-MicroUSB cable</P>\r\n";

	//Second Email notice asking to return items.
	echo "<HR>";
    echo "<H3><STRONG>Second Email - 5 Days After Capstone</STRONG></H3>";
	echo "<P>Dear StudentName,<BR>";
	echo "<TEXTAREA NAME=\"email2\" ROWS=\"5\" COLS=\"80\">".$Email2."</TEXTAREA><BR>";
	echo "Sincerely,<BR>ECE SPN System<BR><BR> Quantity 1 Arduino R3 WiFi<BR> Quantity 2 USB-to-MicroUSB cable</P>\r\n";

	//Third Email notice asking to return items.
	echo "<HR>";
    echo "<H3><STRONG>Third Email - 10 Days After Capstone</STRONG></H3>";
	echo "<P>Dear StudentName,<BR>";
	echo "<TEXTAREA NAME=\"email3\" ROWS=\"5\" COLS=\"80\">".$Email3."</TEXTAREA><BR>";
	echo "Sincerely,<BR>ECE SPN System<BR><BR> Quantity 1 Arduino R3 WiFi<BR> Quantity 2 USB-to-MicroUSB cable</P>\r\n";


	//Display the existing notes and change log.
	echo "<HR>";
    $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM CapstoneConfig AS T1 ORDER BY CreateTime DESC;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." looking up configuration notes ".$SQLQuery);
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing notes: ".$mysqli->error."</DIV>";
        goto CloseForm;
	}

	if ($SQLResult->num_rows > 0) {

		echo "<P><STRONG>Notes and Change Log</STRONG><BR><TEXTAREA NAME=\"notes\" ROWS=\"10\" COLS=\"80\" readonly>";

        $ChangeUser = "";
        $ChangeTime = "";

		while($Fields = $SQLResult->fetch_row() ) {

            //Spit out the change header only if this isn't the same user and timestamp.
            if ( ($Fields[1] != $ChangeUser) || ($Fields[4] != $ChangeTime) ) {
                echo date("\r\nD F j, Y, g:i a", $Fields[1]).", User: ".$Fields[4]."\r\n";
                $ChangeUser = $Fields[1];
                $ChangeTime = $Fields[4];
            }

            //Output the change depending on the field - for notes just spit out the text - everything else it's " X changed to Y".
            if ($Fields[2] == "Notes")
			    echo "Additional note was added:\r\n".$Fields[3]."\r\n";
            else
                echo " ".$Fields[2]." changed to ".$Fields[3]."\r\n";
		}
		echo "</TEXTAREA></P>";
	}

    //Additional notes.
    echo "<P><STRONG>New Notes</STRONG><BR><TEXTAREA NAME=\"newnotes\" ROWS=\"5\" COLS=\"80\"></TEXTAREA></P>";

	//Submit button.
	echo "<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Update\"></P>";

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
    //Function to write a string to the log. Getting permission denied... SE Linux issue?
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Capstone/CapstoneCreateConfig.log"); 
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
	    return;
    }

    //Function to retrieve a specified field for a specified user.
    function RetrieveField($Table, $UID, $Field) {

        global $mysqli;

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM ".$Table." WHERE ID='".$UID."' AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
    		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve user field ".$Field." with ID ".$ID." from ".$Table.": ".$mysqli->error."</DIV>";
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

    //Returns the NetID of the authenticated user, or empty string on error.
    function CASAuthenticateTicket($Ticket) {

        //This has to match the entire URL of the requested page.
        $casGet = "https://cas.rutgers.edu/serviceValidate?ticket=".$Ticket."&service=".urlencode("https://apps.ece.rutgers.edu".$_SERVER['PHP_SELF']);
        $response = file_get_contents($casGet);
        //Retrieve the response and find the NetID.
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


CREATE TABLE `CapstoneConfig` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO CapstoneConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1636945846, "SpringDateUTC", "1686945846");
INSERT INTO CapstoneConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1636945846, "FallDateUTC", "1696945846");
INSERT INTO CapstoneConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1636945846, "ReplyToEmailUID", "51");
INSERT INTO CapstoneConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1636945846, "Email1", "Congratulations on completing your Capstone design experience! Please prepare to return any items that were purchased by the department, or loaned to you by the department. Based on our records, you have the item shown below. Please find either Kevin Wine in ECE 118 or John Scafidi in ECE 116 to return the items. If our records are incorrect and you have returned some or all of the items, please let us know. If you would like to keep your project, please contact the Capsone course faculty member for details. If your project is still in working order and could be used at Rutgers Open House or other events, please return it in tact. Thanks!");
INSERT INTO CapstoneConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1636945846, "Email2", "Please return any items that were purchased by the department, or loaned to you by the department, for your Capstone project. Based on our records, you have the items shown below. Please find either Kevin Wine in ECE 118 or John Scafidi in ECE 116 to return the items. If our records are incorrect and you have returned some or all of the items, please let us know. If you would like to keep your project, please contact the Capsone course faculty member for details. If your project is still in working order and could be used at Rutgers Open House or other events, please return it in tact. Thanks!");
INSERT INTO CapstoneConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1636945846, "Email3", "Based on our records, it looks like you still have the Capstone project items shown below. Please find either Kevin Wine in ECE 118 or John Scafidi in ECE 116 to return the items. If our records are incorrect and you have returned some or all of the items, please let us know. If you would like to keep your project, please contact the Capsone course faculty member for details. If your project is still in working order and could be used at Rutgers Open House or other events, please return it in tact. Thanks!");

-->

