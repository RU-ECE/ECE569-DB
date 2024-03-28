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
	    require('../template/head.php');
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You have to login first in order to access this page.</DIV>";
		goto SendTailer;
	}
    //Moved here to after the login check to avoid error messages printing by PHP.
    $UserDoingUpdateNetID = $_SESSION['netid'];


	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli -> connect_errno) {
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

    //Extract the user's role.
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
   		WriteLog("UserID ".$UserDoingUpdateUID." with access role ".$UserDoingUpdateAccessRole." is trying to search Equipment.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">You do not have access to this page.</DIV>";
		goto CloseSQL;
    }

    //Array of names for equipment status
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

	//Validate all the form fields.
	$Errors = FALSE;
	$Errors |= ValidateFormField(TRUE, "rutags", "RU Tags List", "0123456789\r\n", 0, 10000, FALSE);

	//If there were any field errors they will have to be fixed..
	if ($Errors == TRUE) {
    	echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Please go back using your browsers back button and correct the errors with the form fields.</DIV>";
		goto CloseSQL;
	}

	$RUTags = $_POST["rutags"];

	//Go through all the rows in the file.
	$RowsRead = 0;
	$RUTagsCreated = 0;
	$RUTagErrors = 0;

	$Separator = "\r\n";
	$OldRUTag = strtok($RUTags, $Separator);

	while ($OldRUTag !== false) {

		$RowsRead++;

		//Progress report.
		if ( (($RowsRead) % 250) == 0) {
			WriteLog($RowsRead." RUTags read, ".$RUTagsCreated." RUTags created, ".$RUTagErrors." errors.");
			echo("<P>".$RowsRead." RUTags read, ".$RUTagsCreated." RUTags created, ".$RUTagErrors." errors.</P>");
		}

		//Skip if this is a blank line.
		if ($OldRUTag == "") {
			$OldRUTag = strtok($Separator);
			continue;
		}

		//Search for this item in the ECE inventory.
		$SQLQuery = "SELECT Equip.EID, Equipment.ECETag, Equipment.RUTag, Equipment.Status, Equipment.Make, Equipment.Model, Equipment.Type, Equipment.Location, Equipment.OwnerID FROM Equip
			LEFT JOIN
				(SELECT EID,
					(SELECT Value FROM EquipRecords WHERE Field='ECETag' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS ECETag,
					(SELECT Value FROM EquipRecords WHERE Field='RUTag' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS RUTag,
					(SELECT Value FROM EquipRecords WHERE Field='Status' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Status,
					(SELECT Value FROM EquipRecords WHERE Field='Make' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Make,
					(SELECT Value FROM EquipRecords WHERE Field='Model' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Model,
					(SELECT Value FROM EquipRecords WHERE Field='Type' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Type,
					(SELECT Value FROM EquipRecords WHERE Field='Location' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS Location,
					(SELECT Value FROM EquipRecords WHERE Field='OwnerID' AND ID=Equip.EID ORDER BY CreateTime DESC LIMIT 1) AS OwnerID
				FROM Equip) AS Equipment
	        ON Equip.EID=Equipment.EID
			WHERE RUTag LIKE '%".$OldRUTag."%';";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get equipment list: ".$mysqli->error."</DIV>";
			goto CloseSQL;
		}

		//Display the query results for this RU tag number.
		if ($SQLResult->num_rows > 0) {
			$Fields = $SQLResult->fetch_assoc();
			echo $OldRUTag.", ".$StatusList[$Fields["Status"]].", ".$Fields["Make"]." ".$Fields["Model"]." ".$Fields["Type"].", ".$Fields["Location"].", ".$Fields["OwnerID"]."<BR>\r\n";
		}
		else {
			echo $OldRUTag.", Not Found<BR>\r\n";
		}

		//Get the next line.
		$OldRUTag = strtok($Separator);
	}

	//Import report.
	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">";
	echo $RowsRead." lines processed.<BR>";
	echo "</DIV>";

CloseSQL:
	$mysqli->close();

SendTailer:
    echo "</DIV>";
	echo "</DIV>";

	require('../template/footer.html');
	require('../template/foot.php');
?>


<?php
    //Function to write a string to the log.
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Equpment/EquipmentProcessSearch.log"); 
    }


	//Validates a form field.
	function ValidateFormField($RequiredFlag, $FormField, $DisplayName, $ValidChars, $MinValue, $MaxValue, $NumberFlag) {

	    if (isset($_POST[$FormField])) {
			$FieldValue = $_POST[$FormField];
			$FieldLength = strlen($FieldValue);
		}
		else {
			$FieldValue = "";
			$FieldLength = 0;
		}

		//If the field is blank and not required, do nothing. If it is blank and required, that is an error.
		//This situation comes up because un-checked check boxes and blank fields do not appear in the form data.
		if ($FieldLength == 0) {
			if ($RequiredFlag == TRUE) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." must be provided.</DIV>";
				return TRUE;
			}
			else
				return FALSE;
		}

		//Check for illegal characters.
		if ( ($CharPosition = strspn($FieldValue, $ValidChars)) < $FieldLength) {
			$CharPosition++;
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Illegal character in ".$DisplayName." field \"".$FieldValue."\" at position ".$CharPosition."</DIV>";
			return TRUE;
		}

		if ($NumberFlag == TRUE) {

			//Check maximum length.
			if ($FieldLength > 10) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is ".$FieldLength." digits long, and maximum allowed is 10 digits.</DIV>";
				return TRUE;
			}

			//Check maximum value.
			if ( ($MaxValue > 0) && ($FieldValue > $MaxValue) ) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is beyond maximum value of ".$MaxValue."</DIV>";
				return TRUE;
			}

			//Check minimum value.
			if ( ($MinValue > 0) && ($FieldValue < $MinValue) ) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is below minimum value of ".$MinValue."</DIV>";
				return TRUE;
			}
		}
		else {

			//Check minimum length.
			if ($FieldLength < $MinValue) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is ".$FieldLength." characters long, and minimum required is ".$MinValue." characters.</DIV>";
				return TRUE;
			}

			//Check maximum length.
			if ($FieldLength > $MaxValue) {
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$DisplayName." is ".$FieldLength." characters long, and maximum allowed is ".$MaxValue." characters.</DIV>";
				return TRUE;
			}
		}

		return FALSE;
    }

?>
