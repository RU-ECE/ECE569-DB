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

    //Make sure user is allowed to view this page.
    if ($UserDoingUpdateAccessRole != "S") {
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." trying to create/edit an SPN.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

?>


	<H3>Upload From CSV File</H3>

	<FORM METHOD="POST" ACTION="/SPN/SPNProcessUpload.php" ENCTYPE="multipart/form-data">

		<P>Specify the type, semester and year for this batch of SPNs:<BR>
		<B>Type:</B>
		<SELECT NAME="type">
			<OPTION VALUE="U" SELECTED>Undergraduate</OPTION>
			<OPTION VALUE="G">Graduate</OPTION>
			<OPTION VALUE="G">Special Problems</OPTION>
			<OPTION VALUE="G">Internship</OPTION>
		</SELECT><BR>
		<B>Semester:</B>
		<SELECT NAME="semester">
			<OPTION VALUE="S" SELECTED>Spring</OPTION>
			<OPTION VALUE="M">Summer</OPTION>
			<OPTION VALUE="F">Fall</OPTION>
		</SELECT><BR>
		<B>Year:</B>
		<INPUT TYPE="text" NAME="year" VALUE="2023" SIZE="4" MAXLENGTH="4"><BR>
		</P>
		<P>It is best to first upload the file in preview mode, to make sure there are no problems with the CSV file. If there are problems, some lines of the file may not be processed, and you will have to go back to find those lines and manually enter them, which is very tedious.</P>
		<P>
		<INPUT TYPE="checkbox" NAME="preview" checked>Preview Mode
		</P>

		<P>The input file must be in CSV format with the column contents as shown below. Column headings are not required. SPNs are padded with leading zeros to make them exactly 6 digits. Sections are padded with leading zeros to make them exactly 2 digits.</P>

		<P>
		<TABLE CLASS="table table-striped table-bordered">
			<THEAD CLASS="thead-dark">
				<TR>
					<TH>Curriculum</TH>
					<TH>Course Number</TH>
					<TH>Blank</TH>
					<TH>Section Number</TH>
					<TH>Unknown Number</TH>
					<TH>Course Name</TH>
					<TH>SPN</TH>
				</TR>
			</THEAD>
			<TR>
				<TD>332</TD>
				<TD>221</TD>
				<TD></TD>
				<TD>1</TD>
				<TD>10261</TD>
				<TD>PRIN ELEC ENGG I</TD>
				<TD></TD>
			</TR>
			<TR>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD>009789</TD>
			</TR>
			<TR>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD></TD>
				<TD>128190</TD>
			</TR>

		</TABLE>
		</P>

		<LABEL FOR="fileSelect">Filename:</LABEL>
		<INPUT TYPE="file" NAME="users" ID="fileSelect">
		<INPUT TYPE="submit" NAME="submit" VALUE="Upload SPNs">
	</FORM>

	<HR>

	<H3>Upload From List</H3>

	<FORM METHOD="POST" ACTION="/SPN/SPNProcessBatch.php" ENCTYPE="multipart/form-data">

		<P>Specify the type, semester, year, course number, and section for this batch of SPNs:<BR>
		<P>
		<B>Type:</B>
		<SELECT NAME="type">
			<OPTION VALUE="U" SELECTED>Undergraduate</OPTION>
			<OPTION VALUE="G">Graduate</OPTION>
			<OPTION VALUE="G">Special Problems</OPTION>
			<OPTION VALUE="G">Internship</OPTION>
		</SELECT><BR>
		<B>Semester:</B>
		<SELECT NAME="semester">
			<OPTION VALUE="S" SELECTED>Spring</OPTION>
			<OPTION VALUE="M">Summer</OPTION>
			<OPTION VALUE="F">Fall</OPTION>
		</SELECT><BR>
		<B>Year:</B>
		<INPUT TYPE="text" NAME="year" VALUE="2023" SIZE="4" MAXLENGTH="4"><BR>
		<B>Course Number:</B>
		<INPUT TYPE="text" NAME="course" VALUE="" SIZE="3" MAXLENGTH="3"><BR>
		<B>Course Section:</B>
		<INPUT TYPE="text" NAME="section" VALUE="" SIZE="2" MAXLENGTH="2"><BR>
		</P>
		<P>Paste the SPNs below, one per line. Do not put any commas or extra spaces.<BR>
		<TEXTAREA NAME="spns" ROWS="10" COLS="20"></TEXTAREA><BR>
		<INPUT TYPE="submit" NAME="submit" VALUE="Upload SPNs">
		</P>
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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/SPN/SPNCreateUpload.log"); 
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
