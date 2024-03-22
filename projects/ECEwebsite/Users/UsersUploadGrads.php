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
	$Menu = "Users Upload";
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


	//Check to make sure the file uploaded without errors.
	// Error #1 is "file too large" - bigger than the "upload_max_filesize" in php.ini
	if (!isset($_FILES["users"]) || $_FILES["users"]["error"] != 0) {
		WriteLog("Error ".$_FILES["users"]["error"]." uploading the users data file".$_FILES["users"]["name"]);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$_FILES["users"]["error"]." uploading the users data file ".$_FILES["users"]["name"]."</DIV>";
		goto CloseSQL;
	}

	//Setup an array of the allowed MIME types.
	//When I upload a .csv file, the MIME type apparently is application/vnd.ms-excel, not text/csv
	$AllowedTypes = array("csv" => "application/vnd.ms-excel");

	//Save useful strings.
	$FileName = $_FILES["users"]["name"];
	$FileType = $_FILES["users"]["type"];
	$FileSize = $_FILES["users"]["size"];
	$TempFile = $_FILES["users"]["tmp_name"];

	//Log the upload information, in case this stops working..
	//WriteLog("File uploaded, FileName=".$FileName.", FileType=".$FileType.", FileSize=".$FileSize.", TempFile=".$TempFile);


	//Check the file extension.
	$FileExtension = pathinfo($FileName, PATHINFO_EXTENSION);
	if (!array_key_exists($FileExtension, $AllowedTypes)) {
		WriteLog("File type ".$FileExtension." not allowed.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">File type ".$FileExtension." not allowed. File type must be .csv.</DIV>";
		goto CloseSQL;
	}

	//Check the max file size - currently allowing up to 2MB
	if ($FileSize > 2097152) {
		WriteLog("File is too big, ".$FileSize." bytes.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Maximum file size is 2MB. Your file is ".$FileSize." bytes</DIV>";
		goto CloseSQL;
	}

	if ($FileSize <= 0) {
		WriteLog("File is empty, ".$FileSize." bytes.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Your file is empty!</DIV>";
		goto CloseSQL;
	}

	//Verify the MIME type of the file.
	if (!in_array($FileType, $AllowedTypes)) {
 		WriteLog("MIME type ".$FileType." is not allowed.");
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Incorrect file type of ".$FileType." File must be .csv.</DIV>";
		goto CloseSQL;
	}

	//With the uploaded file still in temporary storage, attempt to insert all the rows in a new SQL table.
	//Open the temporary file for reading.        
	if ( ($hInputFile = fopen($TempFile, "r")) === FALSE) {
		WriteLog("fopen failed on file ".$TempFile);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to process input file.</DIV>";
		goto CloseSQL;
	}

	//The first line has to be read for the column headers.
	$CSVFields = fgetcsv($hInputFile, 1000, ",");

	//Get the index of all the relevant columns in the .csv file.
	if ( ($RUIDIndex = array_search("RUID", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"RUID\" (RUID) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($LastNameIndex = array_search("Last Name", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Last Name\" (Last Name) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($FirstNameIndex = array_search("First Name", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"First Name\" (First Name) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($MiddleNameIndex = array_search("MI", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"MI\" (Middle Name) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($EmailIndex = array_search("Email", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Email\" (Email) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($GenderIndex = array_search("Gender", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Gender\" (Gender) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($CitizenIndex = array_search("Citizen", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Citizen\" (Citizen Status) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($VisaIndex = array_search("Visa", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Visa\" (Visa Status) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($NJResidentIndex = array_search("NJ Res", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"NJ Res\" (NU Residency Status) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($DegreeIndex = array_search("Deg Code", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Deg Code\" (Degree Major) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($TAIndex = array_search("TA", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"TA\" (TA Check) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($GAIndex = array_search("GA", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"GA\" (GA Check) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($FellowIndex = array_search("Fellow", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"Fellow\" (Fellow Check) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($StartTermIndex = array_search("First Yr/Term", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"First Yr/Term\" (Starting Year) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}
	if ( ($NetIDIndex = array_search("NetID", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"NetID\" (NetID) column not found in uploaded file.</DIV>";
		goto SendTailer;
	}


	//Any uploaded CSV file could contain students that:
	// 1-Are already in the database and haven't changed.
	// 2-Are already in the dababase and something has changed (status (have graduated), graduation date, major, name)
	// 3-Are missing from the database - new student
	//It is also possible that an existing student in the database is NOT in the CSV file. This could mean they have graduated, left the program, or changed major.
	//In this situation, their status needs to be changed. I can deduce if they disappeared because they graduated. I can deduce they switched to another engineering.
	//But there is no way to know they left the program for some other reason.

	//This gets the current User Status and Student Type. It was quite a project figuring this out.
	// SELECT UID, (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Status, (SELECT Value FROM UserRecords WHERE Field='StudentType' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type FROM Users;

	//To detect students that have graduated or otherwise left the program, flag all the currently active undergraduate students.
	// The flag will be turned off if they are found in the CSV file, and then any remaining students with the flag still on can be adjusted.
	//UPDATE Users LEFT JOIN (SELECT UID, (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Status, (SELECT Value FROM UserRecords WHERE Field='StudentType' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type FROM Users) AS Students ON Users.UID=Students.UID SET MissingFlag='T' WHERE Students.Status='A' AND Students.Type='U';
	$SQLQuery = "UPDATE Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				(SELECT Value FROM UserRecords WHERE Field='StudentType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type
			FROM Users) AS Students
		ON Users.UID=Students.UID
		SET MissingFlag='T'
		WHERE Students.Status='A' AND (Students.Type='M' OR Students.Type='P');";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to set missing flag on active students: ".$mysqli->error."</DIV>";
		goto CloseFile;
	}

	//Go through all the rows in the file.
	$StudentsRead = 0;
	$StudentsCreated = 0;
	$StudentsUpdated = 0;
	$StudentErrors = 0;


	while (($CSVFields = fgetcsv($hInputFile, 1000, ",")) !== FALSE) {

		$StudentsRead++;

		//Progress report.
		if ( (($StudentsRead) % 250) == 0) {
			WriteLog($StudentsRead." students read, ".$StudentsCreated." students created, ".$StudentsUpdated." students updated, ".$StudentErrors." errors.");
			echo("<P>".$StudentsRead." students read, ".$StudentsCreated." students created, ".$StudentsUpdated." students updated, ".$StudentErrors." errors.</P>");
			//WriteLog("RUID=".$CSVFields[$RUIDIndex].", Name=".$CSVFields[$FullNameIndex].", Email=".$CSVFields[$EmailIndex]);
		}

		//Validate all the incoming CSV fields.
		$Errors = FALSE;

		//NetID
		$Errors |= ValidateField("NetID", $CSVFields[$NetIDIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890", 0, 20);

		//RUID
		$Errors |= ValidateField("RUID", $CSVFields[$RUIDIndex], "1234567890", 0, 20);

		//TA, GA, and Fellow flags
		$Errors |= ValidateField("TA", $CSVFields[$TAIndex], "YN", 0, 1);
		$Errors |= ValidateField("GA", $CSVFields[$GAIndex], "YN", 0, 1);
		$Errors |= ValidateField("Fellow", $CSVFields[$FellowIndex], "YN", 0, 1);
		//Only one of these boxes can be checked..
		if ( ($CSVFields[$TAIndex] == "Y") && ($CSVFields[$GAIndex] != "Y") && ($CSVFields[$FellowIndex] != "Y") )
			$EmployeeType = "T";
		else if ( ($CSVFields[$TAIndex] != "Y") && ($CSVFields[$GAIndex] == "Y") && ($CSVFields[$FellowIndex] != "Y") )
			$EmployeeType = "G";
		else if ( ($CSVFields[$TAIndex] != "Y") && ($CSVFields[$GAIndex] != "Y") && ($CSVFields[$FellowIndex] == "Y") )
			$EmployeeType = "E";
		else if ( ($CSVFields[$TAIndex] != "Y") && ($CSVFields[$GAIndex] != "Y") && ($CSVFields[$FellowIndex] != "Y") )
			$EmployeeType = "";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Illegal TA, GA, Fellow combination.</DIV>";
			$Errors = TRUE;
		}

		//Degree Code
		$Errors |= ValidateField("Degree Code", $CSVFields[$DegreeIndex], "PHDMS", 0, 5);
		//Encode the program.
		// If it's masters, then it's only masters. If its phd, then it is usually masters and phd, but could be just phd. There is no reliable way to tell.
		if ($CSVFields[$DegreeIndex] == "PHD")
			$StudentType = "P";
		else if ($CSVFields[$DegreeIndex] == "MS")
			$StudentType = "M";
		else if ($CSVFields[$DegreeIndex] == "")
			//Assume non-matriculated if blank.
			$StudentType = "N";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown Degree Code.</DIV>";
			$Errors = TRUE;
		}

		//Citizenship - U=US Citizen, F=Foreign, P=Permanent Resident, D=DACA, Z=Unknown
		$Errors |= ValidateField("Citizenship", $CSVFields[$CitizenIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 0, 10);
		if ($CSVFields[$CitizenIndex] == "US")
			$Citizen = "U";
		else if ($CSVFields[$CitizenIndex] == "Foreign")
			$Citizen = "F";
		else if ($CSVFields[$CitizenIndex] == "PR")
			$Citizen = "P";
		else if ($CSVFields[$CitizenIndex] == "DACA")
			$Citizen = "D";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown citizenship.</DIV>";
			$Errors = TRUE;
		}
		//The original CSV field needs to be saved in order to detect changes.

		//Visa type
		//F1, J1, J3, H1, H2, H4 are valid, AP="Advance Parole", FX="spouse of permanent resident", UN is unknown I assume
		$Errors |= ValidateField("Visa Type", $CSVFields[$VisaIndex], "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890- ", 0, 5);
		if ( ($CSVFields[$CitizenIndex] == "US") || ($CSVFields[$CitizenIndex] == "PR") || ($CSVFields[$VisaIndex] == "UN") || ($CSVFields[$VisaIndex] == "XX") )
			$Visa = "";
		else if ($CSVFields[$VisaIndex] == "F1")
			$Visa = "F1";
		else if ($CSVFields[$VisaIndex] == "F2")
			$Visa = "F2";
		else if ($CSVFields[$VisaIndex] == "J1")
			$Visa = "J1";
		else if ($CSVFields[$VisaIndex] == "J3")
			$Visa = "J3";
		else if ($CSVFields[$VisaIndex] == "H1")
			$Visa = "H1";
		else if ($CSVFields[$VisaIndex] == "H2")
			$Visa = "H2";
		else if ($CSVFields[$VisaIndex] == "H4")
			$Visa = "H4";
		else if ($CSVFields[$VisaIndex] == "AP")
			$Visa = "AP";
		else if ($CSVFields[$VisaIndex] == "FX")
			$Visa = "FX";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown visa status.</DIV>";
			$Errors = TRUE;
		}

		//Gender = M=Male, F=Female, Z=Unknown
		$Errors |= ValidateField("Gender", $CSVFields[$GenderIndex], "MFOmfo", 0, 5);
		if ($CSVFields[$GenderIndex] == "M")
			$Gender = "M";
		else if ($CSVFields[$GenderIndex] == "F")
			$Gender = "F";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown gender.</DIV>";
			$Errors = TRUE;
		}

		//First Yr/Term
		$Errors |= ValidateField("First Yr/Term", $CSVFields[$StartTermIndex], "1234567890/", 0, 6);
		//The graduation date can be blank..
		$AdmitMonth = "";
		$AdmitYear = "";
		$GraduationYear = "";
		$DepartureDateUTC = "";
		$StartDateUTC = "";
		$UserStatus = "A";
		if ($CSVFields[$StartTermIndex] != "") {
			//The program start date is in year/month format and needs to be split up so we can calculate an estimated grduation date.
			// $StartTerm[0] will be the year
			// $StartTerm[1] will be the month.
			$StartTerm = explode("/", $CSVFields[$StartTermIndex]);
			$AdmitMonth = $StartTerm[1];
			$AdmitYear = $StartTerm[0];
			//Estimated graduation date is based on the start date and the program type.
			// Masters should take 2 years, phd 5 years which is usually combined masters and phd. There is no reliable way of deducing phd only program.
			// PHP doesn't do the math automatically - you must cast the string to an integer!
			if ($CSVFields[$DegreeIndex] == "PHD")
				$GraduationYear = (int)$StartTerm[0] + 5;
			else if ($CSVFields[$DegreeIndex] == "MS")
				$GraduationYear = (int)$StartTerm[0] + 2;
			else
				$GraduationYear = (int)$StartTerm[0];
			//The last day of the month is gotten by converting midnight of the first day of the month following graduation to UTC, and then subtracting 1 second.
			$DepartureDateUTC = mktime(0, 0, 0, $AdmitMonth + 1, 1, $GraduationYear) - 86400;
			$StartDateUTC = mktime(0, 0, 0, $AdmitMonth, 1, $AdmitYear);

			//Figure out the user status based on graduation month/year and the current date.
			//The UserType was already read from the form fields, and is the same for all uploaded users in this batch.
			if ($DepartureDateUTC < $CurrentTime)
				$UserStatus = "G";
		}

		//Name
		$Errors |= ValidateField("First Name", $CSVFields[$FirstNameIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-'., ", 0, 50);
		$Errors |= ValidateField("Middle Name", $CSVFields[$MiddleNameIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-'., ", 0, 50);
		$Errors |= ValidateField("Last Name", $CSVFields[$LastNameIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-'., ", 0, 50);
		$FirstName = ucwords(strtolower($CSVFields[$FirstNameIndex]), "'- ");
		$MiddleName = ucwords(strtolower($CSVFields[$MiddleNameIndex]), "'- ");
		$LastName = ucwords(strtolower($CSVFields[$LastNameIndex]), "'- ");
		//We have to save the name as it was originally appearing in the CSV, so we can later detect if it was changed.

		//Email
		$Errors |= ValidateField("Official Email", $CSVFields[$EmailIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_~+.@", 0, 80);

		//NJ Residency
		$Errors |= ValidateField("NJ Resident", $CSVFields[$NJResidentIndex], "YN", 0, 1);
		if ($CSVFields[$NJResidentIndex] == "Y")
			$NJResident = "T";
		else if ($CSVFields[$NJResidentIndex] == "N")
			$NJResident = "";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown NJ residency status.</DIV>";
			$Errors = TRUE;
		}


		//If any errors were found with this student print their name so the user knows who to go and fix.
		if ($Errors) {
			echo "<P>Student ".$CSVFields[$FirstNameIndex]." ".$CSVFields[$LastNameIndex]." with RUID ".$CSVFields[$RUIDIndex]." had errors, so none of their data was saved.</P>";
			$StudentErrors++;
			continue;
		}

		//We have to try and figure out if this student is already in the database.
		// Either the NetID or the RUID must match.
		// The check for blank NetID and RUID is to prevent a match on an existing user that somehow has both NetID and RUID blank.
		//SELECT UID, Value FROM UserRecords WHERE Field='NetID' AND Value='".$CSVFields[$NetIDIndex]."' ORDER BY CreateTime DESC LIMIT 1;
		//SELECT UID, Value FROM UserRecords WHERE Field='RUID' AND Value='".$RUID."' ORDER BY CreateTime DESC LIMIT 1;
		//SELECT Users.UID, Students.NetID, Students.RUID FROM Users LEFT JOIN (SELECT UID, (SELECT Value FROM UserRecords WHERE Field='NetID' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID, (SELECT Value FROM UserRecords WHERE Field='RUID' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID FROM Users) AS Students ON Users.UID=Students.UID WHERE (Students.NetID='wine' AND Students.NetID<>'') OR (Students.RUID='1234567890' AND Students.RUID<>'');
		$SQLQuery = "SELECT Users.UID, Students.NetID, Students.RUID FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
					(SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID
				FROM Users) AS Students
			ON Users.UID=Students.UID
			WHERE (Students.NetID='".$CSVFields[$NetIDIndex]."' AND Students.NetID<>'') OR (Students.RUID='".$CSVFields[$RUIDIndex]."' AND Students.RUID<>'');";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			WriteLog("Error ".$mysqli->error." checking for existing user:".$SQLQuery);
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error checking if user exists: ".$mysqli->error."</DIV>";
			$StudentErrors++;
			continue;
		}

		//Make sure only one student was found. I'm sure someone will eventually figure out how to create duplicate users!
		if (mysqli_num_rows($SQLResult) > 1) {
			WriteLog("Duplicate student NetID:".$CSVFields[$NetIDIndex]." RUID:".$CSVFields[$RUIDIndex]." Copies:".mysqli_num_rows($SQLResult));
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Student with NetID ".$CSVFields[$NetIDIndex]." and RUID ".$CSVFields[$RUIDIndex]." is already in database ".mysqli_num_rows($SQLResult)." times.</DIV>";
			$StudentErrors++;
			continue;
		}

		//If student is in database, just have to fix their MissingFlag. If not, create a new user.
		if (mysqli_num_rows($SQLResult) == 1) {

			//A student was found.
			$Fields = $SQLResult->fetch_row();

			//Grab the UID of the user that we are processing.
			$UserBeingUpdatedUID = $Fields[0];

			//Reset their MissingFlag.
			$SQLQuery = "UPDATE Users SET MissingFlag='F' WHERE UID=".$UserBeingUpdatedUID.";";
			$SQLResult = $mysqli->query($SQLQuery);
			if ($mysqli->error) {
				WriteLog("Error ".$mysqli->error." resetting MissingFlag:".$SQLQuery);
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error resetting missing flag: ".$mysqli->error."</DIV>";
				$StudentErrors++;
				continue;
			}
		}
		else {

			//New student. Add this user to the Users table with fields that either don't change or don't need to be logged.
			$SQLQuery = "INSERT INTO Users (
				CreateTime,
				MissingFlag,
				SessionID
			) VALUES (
				'".$CurrentTime."',
				'F',
				'0'
			);";
			$SQLResult = $mysqli->query($SQLQuery);
			if ($mysqli->error) {
				WriteLog("Error ".$mysqli->error." adding user:".$SQLQuery);
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error adding new student: ".$mysqli->error."</DIV>";
				$StudentErrors++;
				continue;
			}

			//Get the ID of this user. I tried to combine this with the query above but that doesn't work.
			$SQLQuery = "SELECT LAST_INSERT_ID();";
			$SQLResult = $mysqli->query($SQLQuery);
			if ($mysqli->error) {
				WriteLog("Error ".$mysqli->error." adding user:".$SQLQuery);
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error get UID of new student: ".$mysqli->error."</DIV>";
				$StudentErrors++;
				continue;
			}
			$Fields = $SQLResult->fetch_row();
			$UserBeingUpdatedUID = $Fields[0];

			$StudentsCreated++;
		}

		//Update all the fields. 
		$ChangeFlag = FALSE;
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "NetID", $CSVFields[$NetIDIndex]);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "RUID", $CSVFields[$RUIDIndex]);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "EmployeeType", $EmployeeType);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "StudentType", $StudentType);
		//Only update citizenship status if it has changed in the CSV.
		if ($ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVCitizen", $CSVFields[$CitizenIndex]))
			UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Citizen", $Citizen);
//SHADOW COPY OF VISA TOO??
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Visa", $Visa);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Gender", $Gender);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "DepartureDateUTC", $DepartureDateUTC);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "StartDateUTC", $StartDateUTC);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVAdmitYear", $AdmitYear);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVAdmitMonth", $AdmitMonth);
		//The first and last names should only be updated if the CSV shadow copies have been changed in the CSV.
		if ($ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVFirstName", $CSVFields[$FirstNameIndex]))
			UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "FirstName", $FirstName);
		if ($ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVMiddleName", $CSVFields[$MiddleNameIndex]))
			UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "MiddleName", $MiddleName);
		if ($ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVLastName", $CSVFields[$LastNameIndex]))
			UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "LastName", $LastName);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "OfficialEmail", $CSVFields[$EmailIndex]);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "PreferredEmail", $CSVFields[$EmailIndex]);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "NJResident", $NJResident);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "UserStatus", $UserStatus);
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "AccessRole", "D");
		$ChangeFlag |= UpdateField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Major", "332");

		//If something was changed, then log another updated student.
		if ($ChangeFlag)
			$StudentsUpdated++;
	}


	//At this point, all the existing users have been updated and new users created, but it's not over yet.
	// Any active users in the table that weren't found in the CSV file need to be handled.
	//SELECT Users.UID, Users.MissingFlag, Students.Type, Students.Status, Students.DepartureDateUTC FROM Users LEFT JOIN (SELECT UID, (SELECT Value FROM UserRecords WHERE Field='StudentType' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type, (SELECT Value FROM UserRecords WHERE Field='UserStatus' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Status, (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC FROM Users) AS Students ON Users.UID=Students.UID WHERE Users.MissingFlag='T' AND Students.Status='A' AND (Students.Type='M' OR Students.Type='P');
	$SQLQuery = "SELECT Users.UID, Users.MissingFlag, Students.Type, Students.Status, Students.DepartureDateUTC FROM Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='StudentType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type,
				(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				(SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC
			FROM Users) AS Students
		ON Users.UID=Students.UID
		WHERE Users.MissingFlag='T' AND Students.Status='A' AND (Students.Type='M' OR Students.Type='P');";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get active students with missing flag set: ".$mysqli->error."</DIV>";
		goto CloseFile;
	}

	$StudentsGraduated = 0;
	$StudentsMissing = 0;
	$MissingErrors = 0;

	//If no missing students were found, we are done.
	if (mysqli_num_rows($SQLResult) != 0) {

		//Go through all the active students that are missing from the CSV file and figure out what to do with them.
		//At a minimum, the missing flag has to be set back to false.
		while ($Fields = $SQLResult->fetch_assoc() ) {

			//It is possible the student has graduated..
			if ($CurrentTime > $Fields["DepartureDateUTC"]) {
				$StudentsGraduated++;

				//Update the status by creating a new status field record..
				$SQLQuery = "INSERT INTO UserRecords (ID, CreateUID, CreateTime, Field, Value) VALUES (".$Fields["UID"].",".$UserDoingUpdateUID.",".$CurrentTime.",'UserStatus','G');";
				$SQLResultInner = $mysqli->query($SQLQuery);
				if ($mysqli->error) {
					//Hummm. what to do.. The missing flag will still be set but I guess it doesn't matter.
					WriteLog("Error ".$mysqli->error." updating missing user ".$SQLQuery);
					$MissingErrors++;
				}

				//Reset the missing flag for this student.
				$SQLQuery = "UPDATE Users SET MissingFlag='F' WHERE UID='".$Fields['UID']."';";
				$SQLResultInner = $mysqli->query($SQLQuery);
				if ($mysqli->error) {
					//Hummm. what to do.. The missing flag will still be set but I guess it doesn't matter.
					WriteLog("Error ".$mysqli->error." updating missing user ".$SQLQuery);
					$MissingErrors++;
				}
			}
			else {
				//Nothing else is deducible... user intervention is required to set their actual status.
				$StudentsMissing++;
			}
		}
	}

	//Import report.
	$StudentsUpdated -= $StudentsCreated;
	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">";
	echo "Students read in from CSV file <STRONG>".$FileName."</STRONG>:".$StudentsRead."<BR>";
	echo "New students created in database:".$StudentsCreated." <BR>";
	echo "Existing students updated: ".$StudentsUpdated."<BR>";
	echo "Students with import errors that were not processed:".$StudentErrors."<BR>";
	echo "Students missing from CSV file that have been changed to graduated status:".$StudentsGraduated."<BR>";
	echo "Students missing from CSV file whose status is unknown:".$StudentsMissing."<BR>";
	echo "Errors handling missing students:".$MissingErrors;
	echo "</DIV>";


CloseFile:
	fclose($hInputFile);

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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersUploadGrads.log"); 
    }


	//Validates a CSV field.
    function ValidateField($FieldName, $CSVValue, $ValidChars, $MinLength, $MaxLength) {
	 
		global $mysqli;


		//This is needed in several places..
		$FieldLength = strlen($CSVValue);

		//If the field is blank there is no point in saving it.
		if ($FieldLength <= 0)
			return FALSE;

		//Check minimum length.
		if ($FieldLength < $MinLength) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$FieldName." is ".$FieldLength." characters long, and minimum required is ".$MinLength." characters.</DIV>";
			return TRUE;
		}

		//Check maximum length.
		if ($FieldLength > $MaxLength) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">".$FieldName." is ".$FieldLength." characters long, and maximum allowed is ".$MaxLength." characters.</DIV>";
			return TRUE;
		}

		//Check for illegal characters.
		if ( ($CharPosition = strspn($CSVValue, $ValidChars) ) < $FieldLength) {
			$CharPosition++;
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Illegal character in ".$FieldName." field \"".$CSVValue."\" at position ".$CharPosition."</DIV>";
			return TRUE;
		}

		return FALSE;
    }


 	//Stores the field to a name/value record in the change record table.
	// Returns TRUE if the record was stored, FALSE if it wasn't or there was an error.
	// The return value is used to determine if there was a change to the field.
	function UpdateField($UserBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

		global $mysqli;


		//Before writing a new change record, we have to see if this record already exists and if the value is different.
		// I was going to do this with INSERT IGNORE, but there is no way to avoid writing blank fields that don't already exist,
		// and there is also the possibility that the new field value matches an old value that we are changing back to.

		$SQLQuery = "SELECT Value FROM UserRecords WHERE ID=".$UserBeingUpdated." AND Field='".$FieldName."' ORDER BY CreateTime DESC LIMIT 1;";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to retrieve change record: ".$mysqli->error."</DIV>";
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
		$SQLQuery = "INSERT INTO UserRecords (
			ID,
			CreateUID,
			CreateTime,
			Field,
			Value
		) VALUES (
			'".$UserBeingUpdated."',
			'".$UserDoingUpdate."',
			'".$TimeStamp."',
			'".$FieldName."',
			'".mysqli_real_escape_string($mysqli, $FieldValue)."'
		);";
		$SQLResult = $mysqli->query($SQLQuery);
		if ($mysqli->error) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to add change record: ".$mysqli->error."</DIV>";
			return FALSE;
		}

		return TRUE;
    }
?>
