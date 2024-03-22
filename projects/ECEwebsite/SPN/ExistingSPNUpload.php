<?php	

	$CurrentTime = time();

	$Filename = "F2023SPNRequests.csv";
	$SPNFile = fopen($Filename, "r") or die("Unable to open file!");

	//Connect to MySQL server.
    require('../include/config.php');
	$mysqli = new mysqli($dbhost, $dbuser, $dbpw, $db);
    if($mysqli -> connect_errno) {
		echo "Failed to connect to SQL server: ".$mysqli->connect_error."\r\n";
		exit();
	}

	//Go through all the rows in the file.
	$RowsRead = 0;
	$SPNRequestsCreated = 0;
	while (($CSVFields = fgetcsv($SPNFile, 1000, ",")) !== FALSE) {

		$RowsRead++;

		//Progress report.
		if ( (($RowsRead) % 250) == 0) {
			echo $RowsRead." SPNRequests read, ".$SPNRequestsCreated." SPN created.";
		}

		//Skip blank rows.
		if ( ($CSVFields[0] == "") && ($CSVFields[1] == "") && ($CSVFields[2] == "") && ($CSVFields[3] == "") && ($CSVFields[4] == "") && ($CSVFields[5] == "") && ($CSVFields[6] == "") && ($CSVFields[7] == "")  && ($CSVFields[8] == "") )
			continue;


		echo $CSVFields[0].", ".$CSVFields[1].", ".$CSVFields[2].", ".$CSVFields[3].", ".$CSVFields[4].", ".$CSVFields[5].", ".$CSVFields[6].", ".$CSVFields[7].", ".$CSVFields[8]."\r\n";

		//Convert the timestamp to UTC.
		$Timestamp = strtotime($CSVFields[0]);

		$CourseString = explode( ":", $CSVFields[8], 4);
		$Course = $CourseString[2];
		$Section = $CourseString[3];

		//Lookup their UID based on Rutgers ID.
		$SQLQuery = "SELECT ID FROM UserRecords WHERE Field='RUID' AND Value='".$CSVFields[4]."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "SQL error ".$mysqli->error." on query ".$SQLQuery."\r\n";
			continue;
		}

		//Check if user was found.
		if ($SQLResult->num_rows < 1) {
   			echo "*******Creating User with NetID ".$CSVFields[5]."\r\n";

			$SQLQuery = "INSERT INTO Users (
				CreateTime,
				MissingFlag,
				SessionID
			) VALUES (
				'".$Timestamp."',
				'F',
				'0'
			);";
			$SQLResult = $mysqli->query($SQLQuery);
			if ($mysqli->error) {
				echo "*****Failed to create new user: ".$mysqli->error."\r\n";
				continue;
			}

			//Get the ID of this user. I tried to combine this with the query above but that doesn't work.
			$SQLQuery = "SELECT LAST_INSERT_ID();";
			$SQLResult = $mysqli->query($SQLQuery);
			if ($mysqli->error) {
				echo "****Failed to get ID of new user: ".$mysqli->error."\r\n";
				continue;
			}
			$Fields = $SQLResult->fetch_row();
			$UserBeingUpdatedUID = $Fields[0];

			//Finally we can update all the fields. Blank fields will not be stored, unless there was an existing field (user may want to blank the field).	
			$ChangeFlag = FALSE;
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "NetID", $CSVFields[5]);
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "RUID", $CSVFields[4]);
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "UserStatus", "A");
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "AccessRole", "D");
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "StudentType", "U");
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "FirstName", $CSVFields[3]);
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "LastName", $CSVFields[2]);
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "OfficialEmail", $CSVFields[5]."@scarletmail.rutgers.edu");
			$ChangeFlag |= UpdateField("UserRecords", $UserBeingUpdatedUID, $UserBeingUpdatedUID, $Timestamp, "PreferredEmail", $CSVFields[6]);

			$UID = $UserBeingUpdatedUID;

			echo "User added: ".$CSVFields[3]." ".$CSVFields[2].", NetID: ".$CSVFields[5].", RUID: ".$CSVFields[4].", Email: ".$CSVFields[6]."\r\n";
		}
		else {
			//Extract the UID.
			$Fields = $SQLResult->fetch_row();
			$UID = $Fields[0];
		}

		$CourseName = $CSVFields[9];
		$Reason = $CSVFields[11];
		$Notes = $CSVFields[1];
		$Title = $CSVFields[9];
		$Status = $CSVFields[10];


		echo $Timestamp.", ".$Course.", ".$Section.", ".$UID."\r\n";



		//Has to be an SPN line, so grab it.
		//During the csv file processing, the leading zeros get cut off the SPN, so fix that.
		$SPN = str_pad($CSVFields[8], 6, "0", STR_PAD_LEFT);

		//Store the new SPN Request to the table.
		$SQLQuery = "INSERT INTO SPNRequests (
			CreateTime
		) VALUES (
			'".$Timestamp."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "Error ".$mysqli->error." adding SPN:".$SQLQuery."\r\n";
			continue;
		}

		//Get the ID of this user. I tried to combine this with the query above but that doesn't work.
		$SQLQuery = "SELECT LAST_INSERT_ID();";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "Error ".$mysqli->error." adding SPN:".$SQLQuery,"\r\n";
			continue;
		}
		$Fields = $SQLResult->fetch_row();
		$SPNBeingCreatedID = $Fields[0];


		//Save all the fields in the records table.
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Status", $Status);
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Degree", "U");
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "UserID", $UID);
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Title", $Title);
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Course", $Course);
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Section", $Section);
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Year", "2023");
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Semester", "F");
		UpdateField("SPNRequestRecords", "46", $UID, $CurrentTime, "Notes", $Notes);
		UpdateField("SPNRequestRecords", $SPNBeingCreatedID, $UID, $Timestamp, "Notes", $Reason);


		$SPNRequestsCreated++;
	}

	//Import report.
	echo $RowsRead." lines processed from CSV file ".$Filename."\r\n";
	echo $SPNRequestsCreated." new SPN Requests created in database.\r\n";


	fclose($SPNFile);


 	//Stores the field to a name/value record in the change record table.
	// Returns TRUE if the record was stored, FALSE if it wasn't or there was an error.
	// The return value is used to determine if there was a change to the field.
	function UpdateField($Table, $SPNBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM ".$Table." WHERE ID=".$SPNBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "Failed to retrieve change record: ".$mysqli->error."\r\n";
			return FALSE;
		}

		//New field blank, Old field exists -> Write new record
		//New field blank, Old field not exists -> Do nothing
		//New field has text, Old field exists -> Write a new record if value is different
		//New field has text, Old field not exists -> Write a new record

		//This is the case of the field not in the database and the new field is either non-existant or blank.
		if ( ($SQLResult->num_rows < 1) && (strlen($FieldValue) == 0) )
			return FALSE;

		//This is the case of the field already in the database and it matches the new field.
		if ($SQLResult->num_rows == 1) {
			$Fields = $SQLResult->fetch_row();
			if ($Fields[0] == $FieldValue)
				return FALSE;
		}

		//If we end up here it is OK to go ahead and save the new name/value pair.
		$SQLQuery = "INSERT INTO ".$Table." (
			ID,
			CreateUID,
			CreateTime,
			Field,
			Value
		) VALUES (
			'".$SPNBeingUpdated."',
			'".$UserDoingUpdate."',
			'".$TimeStamp."',
			'".$FieldName."',
			'".mysqli_real_escape_string($mysqli, $FieldValue)."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "Failed to add change record: ".$mysqli->error."\r\n";
			return FALSE;
		}

		return TRUE;
    }
?>
