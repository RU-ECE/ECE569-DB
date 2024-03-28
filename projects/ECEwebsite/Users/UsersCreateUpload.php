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

	echo '<DIV ID="content">';
    echo '<DIV CLASS="container">';

    //Access checks.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to upload users.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have permission to edit this user.</DIV>";
		goto CloseSQL;
    }

?>


	<P>This page allows you to perform periodic uploads of graduate and undergraduate student data.</P>
		
	<P>If an uploaded student is not already in the database, a new record will be created.</P>
		
	<P>If an uploaded student is already in the database, any existing fields that are different from the uploaded fields will be updated. Existing fields that are still the same will not be touched, preserving any changes we have made.</P>
		
	<P>If an existing student in the database is missing from the uploaded students, the system will attempt to determine what happened to them (most likely they graduated) and adjust their status accordingly.</P>
		
	<P>The accidental re-upload of a file that was already uploaded will not duplicate or change any data. All changes to all data are recorded in a <A HREF="https://apps.ece.rutgers.edu/Config/ConfigListChanges.php">change log</A>. Uploads can be un-done in case of an accident such as upload of an old file or mixed up column headings.<P>

	<HR>

	<H2>Upload Undergraduate Students</H2>

	<P>The input file must be in CSV format with the very first row containing the column names. The columns can be in any order. The required columns are shown in the example below, and are case-sensitive. Extra columns are OK and will be ignored. You should be able to upload the SOE undergraduate student spreadsheet (saved in CSV format) with no extra pre-processing.</P>

	<P>
	<TABLE CLASS="table table-striped table-bordered">
		<THEAD CLASS="thead-dark">
			<TR>
				<TH>S Rutgers Id</TH>
				<TH>S Name Last</TH>
				<TH>S Name First</TH>
				<TH>T Expected Month Of Graduation</TH>
				<TH>T Curric Cd1</TH>
				<TH>T Opt Cd1</TH>
				<TH>S Gender Cd</TH>
				<TH>E Email Addr</TH>
				<TH>E Netid</TH>
				<TH>A Number And Street</TH>
				<TH>A Addtnl Info</TH>
				<TH>A City</TH>
				<TH>A State Territory Cd</TH>
				<TH>A Zip Code</TH>
				<TH>A Tele No</TH>
				<TH>S Curr Admit Year</TH>
			</TR>
		</THEAD>
 
		<TR>
			<TD>178001501</TD>
			<TD>RODRIGUES</TD>
			<TD>ANTHONY</TD>
			<TD>5</TD>
			<TD>332</TD>
			<TD>A</TD>
			<TD>M</TD>
			<TD>ar1423@scarletmail.rutgers.edu</TD>
			<TD>ar1423</TD>
			<TD>25 Sycamore Ave.</TD>
			<TD>Apt 2A</TD>
			<TD>New Brunswick</TD>
			<TD>NJ</TD>
			<TD>08901</TD>
			<TD>732-125-0090</TD>
			<TD>2019</TD>
		</TR>
	</TABLE>
	</P>

	<FORM METHOD="POST" ACTION="/Users/UsersUploadUgrads.php" ENCTYPE="multipart/form-data">

		<P>It is best to first upload the file in preview mode, to make sure there are no problems with the CSV file. If there are problems, some lines of the file may not be processed, and you will have to go back to find those lines and manually enter them, which is very tedious.</P>
		<P>
			<INPUT TYPE="checkbox" NAME="preview" checked>Preview Mode
		</P>

		<LABEL FOR="fileSelect">Filename:</LABEL>
		<INPUT TYPE="file" NAME="users" ID="fileSelect">
		<INPUT TYPE="submit" NAME="submit" VALUE="Upload Students">
	</FORM>

	<HR>

	<H2>Upload Graduate Students</H2>

	<P>The input file must be in CSV format with the very first row containing the column names. The columns can be in any order. The required columns are shown in the example below, and are case-sensitive. Extra columns are OK and will be ignored. You should be able to upload the graduate student spreadsheet from Grad Portal (saved in CSV format) with no extra pre-processing.</P>

	<P>

	<TABLE class="table table-striped table-bordered">
		<THEAD class="thead-dark">
			<TR>
				<TH>RUID</TH>
				<TH>Last Name</TH>
				<TH>First Name</TH>
				<TH>MI</TH>
				<TH>Email</TH>
				<TH>Gender</TH>
				<TH>Citizen</TH>
				<TH>Visa</TH>
				<TH>NJ Res</TH>
				<TH>Deg Code</TH>
				<TH>TA</TH>
				<TH>GA</TH>
				<TH>Fellow</TH>
				<TH>First Yr/Term</TH>
				<TH>NetID</TH>
			</TR>
		</THEAD>
 
		<TR>
			<TD>054006299</TD>
			<TD>Abdelbaky</TD>
			<TD>Melissa</TD>
			<TD>M</TD>
			<TD>melissa.romanus@rutgers.edu</TD>
			<TD>F</TD>
			<TD>US</TD>
			<TD>&nbsp;</TD>
			<TD>Y</TD>
			<TD>PHD</TD>
			<TD>N</TD>
			<TD>N</TD>
			<TD>N</TD>
			<TD>2021/7</TD>
			<TD>mmr205</TD>
		</TR>
	</TABLE>
	</P>

	<FORM METHOD="POST" ACTION="/Users/UsersUploadGrads.php" ENCTYPE="multipart/form-data">

		<P>It is best to first upload the file in preview mode, to make sure there are no problems with the CSV file. If there are problems, some lines of the file may not be processed, and you will have to go back to find those lines and manually enter them, which is very tedious.</P>
		<P>
			<INPUT TYPE="checkbox" NAME="preview" checked>Preview Mode
		</P>

		<LABEL FOR="fileSelect">Filename:</LABEL>
		<INPUT TYPE="file" NAME="users" ID="fileSelect">
		<INPUT TYPE="submit" NAME="submit" VALUE="Upload Students">
	</FORM>


<?php

	echo '</DIV>';
	echo '</DIV>';


CloseSQL:
	$mysqli->close();

SendTailer:
	require('../template/footer.html');
	require('../template/foot.php');


    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersCreateUpload.log"); 
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
Blank User tables

CREATE TABLE `Users` (
  `UID` INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `CreateTime` INT,
  `MissingFlag` CHAR(1),
  `SessionID` VARCHAR(50)
);

CREATE TABLE `UserRecords` (
  `ID` INT NOT NULL,
  `CreateUID` INT NOT NULL,
  `CreateTime` INT,
  `Field` VARCHAR(20),
  `Value` VARCHAR(2000),
  INDEX (ID)
);
-->