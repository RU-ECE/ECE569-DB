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
	$Menu = "SPN Configuration";
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

	//Get the UserID of the person whose email should appear in the "Reply-To" field of automated emails.
	$ReplyToEmailUID = RetrieveField("SPNConfig", "0", "ReplyToEmailUID");
	$ReplyToNetID = RetrieveField("UserRecords", $ReplyToEmailUID, "NetID");
	$ReplyToEmail = RetrieveField("UserRecords", $ReplyToEmailUID, "PreferredEmail");

	//Get the UserID of the person whose will receive email notice when a new SPN request is submitted.
	$RequestEmailUID = RetrieveField("SPNConfig", "0", "RequestEmailUID");
	$RequestNetID = RetrieveField("UserRecords", $RequestEmailUID, "NetID");
	$RequestEmail = RetrieveField("UserRecords", $RequestEmailUID, "PreferredEmail");

	//Get the UserID of the SOE approver.
	$SOEApproverUID = RetrieveField("SPNConfig", "0", "SOEApproverUID");
	$SOEApproverNetID = RetrieveField("UserRecords", $SOEApproverUID, "NetID");
	$SOEApproverEmail = RetrieveField("UserRecords", $SOEApproverUID, "PreferredEmail");

	//Get the stock email text.
	$Email1 = RetrieveField("SPNConfig", "0", "Email1");
	$Email2 = RetrieveField("SPNConfig", "0", "Email2");
	$Email3 = RetrieveField("SPNConfig", "0", "Email3");
	$Email4 = RetrieveField("SPNConfig", "0", "Email4");


    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit SPN request.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

	echo "<FORM METHOD=\"POST\" ACTION=\"SPNProcessConfig.php\">";

    echo "<P><STRONG>ReplyTo NetID</STRONG><BR>The NetID of the user whose email address should be used in the Reply-To: field of automated emails.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"replytonetid\" MAXLENGTH=\"25\" SIZE=\"25\" VALUE=\"".$ReplyToNetID."\"> ";
	echo $ReplyToEmail."</P>\r\n";

    echo "<P><STRONG>Request Email NetID</STRONG><BR>The NetID of the user who will receive email notice when a new SPN request is submitted.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"requestnetid\" MAXLENGTH=\"25\" SIZE=\"25\" VALUE=\"".$RequestNetID."\"> ";
	echo $RequestEmail."</P>\r\n";

    echo "<P><STRONG>SOE Approver NetID</STRONG><BR>The NetID of the SOE staff member who approves SPN requests from non-ECE students.<BR>";
    echo "<INPUT TYPE=\"text\" NAME=\"soeapprovernetid\" MAXLENGTH=\"25\" SIZE=\"25\" VALUE=\"".$SOEApproverNetID."\"> ";
	echo $SOEApproverEmail."</P>\r\n";

	//Email notice when SPN request is approved.
	echo "<HR>";
    echo "<H3><STRONG>Approval Email</STRONG></H3>";
	echo "<P>Dear StudentFirstName,<BR>";
	echo "<TEXTAREA NAME=\"email1\" ROWS=\"5\" COLS=\"80\">".$Email1."</TEXTAREA><BR>";
	echo "Course: 220<BR>Section: 01<BR>Semester: Fall 2023<BR>SPN: 123456<BR><BR>Sincerely,<BR>ECE SPN System</P>\r\n";

	//Email notice when SPN request is pending a space increase.
	echo "<HR>";
    echo "<H3><STRONG>Pending Email</STRONG></H3>";
	echo "<P>Dear StudentFirstName,<BR>";
	echo "<TEXTAREA NAME=\"email2\" ROWS=\"5\" COLS=\"80\">".$Email2."</TEXTAREA><BR>";
	echo "Course: 220<BR>Section: 01<BR>Semester: Fall 2023<BR><BR>Sincerely,<BR>ECE SPN System</P>\r\n";

	//Email notice when SPN request is denied.
	echo "<HR>";
    echo "<H3><STRONG>Denied Email</STRONG></H3>";
	echo "<P>Dear StudentFirstName,<BR>";
	echo "<TEXTAREA NAME=\"email3\" ROWS=\"5\" COLS=\"80\">".$Email3."</TEXTAREA><BR>";
	echo "Course: 220<BR>Section: 01<BR>Semester: Fall 2023<BR><BR>Sincerely,<BR>ECE SPN System</P>\r\n";

	//Email notice to SOE approver.
	echo "<HR>";
    echo "<H3><STRONG>SOE Approver Email</STRONG></H3>";
	echo "<TEXTAREA NAME=\"email4\" ROWS=\"5\" COLS=\"80\">".$Email4."</TEXTAREA><BR>";
	echo "Student Name: John Smith<BR>NetID/RUID: js000, 12345678<BR>Major: 650<BR>Course: 14:332:220:01, Principles of EE 1<BR>Semester: Fall 2023<BR>\r\n";

	//Display the existing notes and change log.
	echo "<HR>";
    $SQLQuery = "SELECT T1.CreateUID, T1.CreateTime, T1.Field, T1.Value, (SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=T1.CreateUID ORDER BY CreateTime DESC LIMIT 1) FROM SPNConfig AS T1 ORDER BY CreateTime DESC;";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." looking up configuration notes ".$SQLQuery);
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve existing notes: ".$mysqli->error."</DIV>";
        goto CloseForm;
	}

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNCreateConfig.log"); 
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


		//This has to be done this way, otherwise if UID is zero, empty string will be returned.
		if (strlen($UID) == 0)
			return "";

	    //Retrieve the most recent value of the requested field.
        $SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$UID." AND Field='".$Field."' ORDER BY CreateTime DESC LIMIT 1;";
	    $SQLResult = $mysqli->query($SQLQuery);
	    if ($mysqli->error) {
		    WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
	        echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve field ".$Field." from database: ".$mysqli->error."</DIV>";
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

CREATE TABLE `SPNConfig` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);

INSERT INTO SPNConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1646945846, "ReplyToEmailUID", "46");
INSERT INTO SPNConfig (ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1646945846, "RequestEmailUID", "51");
INSERT INTO SPNConfig(ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1644439150, "Email1", "Your request for an SPN has been approved and is shown below.");
INSERT INTO SPNConfig(ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1644439150, "Email2", "Although there is currently no additional space available in the course you requested below, we are working to increase the capacity of the course. If successful, you will receive another email with an SPN.");
INSERT INTO SPNConfig(ID, CreateUID, CreateTime, Field, Value) VALUES (0, 51, 1644439150, "Email3", "Unfortunately, there is no additional space available in the course shown below. Space sometimes becomes available in the first few weeks of the semester as some students drop the course, so try to register periodically during that time.");

-->

