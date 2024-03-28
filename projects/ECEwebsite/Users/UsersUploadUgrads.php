<?php






//******* JUST FOUND "T Class Composite Cd" IN THE MASTER SPREADSHEET WHICH IS NOT ALWAYS ADMIT YEAR PLUS 4!!!!!
//******* IT IS NOT ALWAYSS 4 YEARS BEYOND THE CURR ADMIT year!!!!
//******* CHANGE THIS FOR THE NEXT UPLOAD??


//SHOULDN'T THIS STILL BE UpdateField()!!!???   when saving fileds, I think I need to use Update... line 505



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


	//Sort out the preview mode and set a flag.
	$PreviewCheck = "";
	if (isset($_POST["preview"]))
		if (ValidateField("Preview Mode", $_POST["preview"], "on", 0, 2) == FALSE)
			if ($_POST["preview"] != "")
				$PreviewCheck = "checked";

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
//	$AllowedTypes = array("csv" => "application/vnd.ms-excel");
	$AllowedTypes = array("csv" => "text/csv");

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

//******* JUST FOUND "T Class Composite Cd" IN THE MASTER SPREADSHEET WHICH IS NOT ALWAYS ADMIT YEAR PLUS 4!!!!!
//******* IT IS NOT ALWAYSS 4 YEARS BEYOND THE CURR ADMIT year!!!!
//******* CHANGE THIS FOR THE NEXT UPLOAD??

	//Get the index of all the relevant columns in the .csv file.
	if ( ($RUIDIndex = array_search("S Rutgers Id", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"S Rutgers Id\" (RUID) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}

	if ( ($LastNameIndex = array_search("S Name Last", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"S Name Last\" (Last Name) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($FirstNameIndex = array_search("S Name First", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"S Name First\" (First Name) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}

	if ( ($GradMonthIndex = array_search("T Expected Month Of Graduation", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"T Expected Month Of Graduation\" (Graduation Month) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($MajorIndex = array_search("T Curric Cd1", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"T Curric Cd1\" (Curriculum Code) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	//This is the degree option - CE or EE.
	if ( ($TrackIndex = array_search("T Opt Cd1", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"T Opt Cd1\" (Curriculum Option Code) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($GenderIndex = array_search("S Gender Cd", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"S Gender Cd\" (Gender) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($EmailIndex = array_search("E Email Addr", $CSVFields)) === FALSE) {
		echo "<P><H3>\"E Email Addr\" (E-Mail) column not found in uploaded file.</H3></P>";
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\"></DIV>";
		goto CloseFile;
	}
	if ( ($NetIDIndex = array_search("E Netid", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"E Netid\" (NetID) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}

	if ( ($Street1Index = array_search("A Number And Street", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"A Number And Street\" (Street Address) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($Street2Index = array_search("A Addtnl Info", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"A Addtnl Info\" (Street Address Second Line) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($CityIndex = array_search("A City", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"A City\" (City) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($StateIndex = array_search("A State Territory Cd", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"A State Territory Cd\" (State) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($ZipIndex = array_search("A Zip Code", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"A Zip Code\" (ZIP Code) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($Phone1Index = array_search("A Tele No", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"A Tele No\" (Phone Number) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($AdmitYearIndex = array_search("S Curr Admit Year", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"S Curr Admit Year\" (Admission Year) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	if ( ($AdmitMonthIndex = array_search("S Curr Admit Term", $CSVFields)) === FALSE) {
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">\"S Curr Admit Term\" (Admission Month) column not found in uploaded file.</DIV>";
		goto CloseFile;
	}
	//WriteLog("RUIDIndex=".$RUIDIndex.", NameIndex=".$FullNameIndex.", GradYearIndex=".$StartYearIndex.", EmailIndex=".$EmailIndex);


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
		WHERE Students.Status='A' AND Students.Type='U';";
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

	$StudentsFound = 0;


	while (($CSVFields = fgetcsv($hInputFile, 1000, ",")) !== FALSE) {

		$StudentsRead++;

		//Progress report.
		if ( (($StudentsRead) % 250) == 0) {
			WriteLog($StudentsRead." students read, ".$StudentsCreated." students created, ".$StudentsUpdated." students updated, ".$StudentErrors." errors.");
			echo($StudentsRead." students read, ".$StudentsCreated." students created, ".$StudentsUpdated." students updated, ".$StudentErrors." errors.<BR>");
			//WriteLog("RUID=".$CSVFields[$RUIDIndex].", Name=".$CSVFields[$FullNameIndex].", Email=".$CSVFields[$EmailIndex]);
		}

		//Validate all the incoming CSV fields.
		$Errors = FALSE;

		//RUID
		$Errors |= ValidateField("RUID", $CSVFields[$RUIDIndex], "1234567890", 1, 20);

		//Name. Now the name is in two separate fields.
		//We have to save the name as it was originally appearing in the CSV, so we can later detect if it was changed.
		$Errors |= ValidateField("First Name", $CSVFields[$FirstNameIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-'., ", 0, 50);
		$Errors |= ValidateField("Last Name", $CSVFields[$LastNameIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-'., ", 0, 50);
		$FirstName = ucwords(strtolower($CSVFields[$FirstNameIndex]), "'- ");
		$LastName = ucwords(strtolower($CSVFields[$LastNameIndex]), "'- ");

		//Graduation Date
		//User Status
		$UserStatus = "A";
		$Errors |= ValidateField("Admission Year", $CSVFields[$AdmitYearIndex], "1234567890", 0, 4);
		$Errors |= ValidateField("Admission Month", $CSVFields[$AdmitMonthIndex], "1234567890", 0, 4);
		$Errors |= ValidateField("Graduation Month", $CSVFields[$GradMonthIndex], "1234567890", 0, 2);
		//Convert graduation month/year to UTC.
		//The graduation year is found by adding 4 years to the entry year. For some reason this didn't work when I tried to do the math in the function field!!!
		$AdmitYear = (int)$CSVFields[$AdmitYearIndex];
		$GraduationMonth = (int)$CSVFields[$GradMonthIndex];
		//The last day of the month is gotten by converting midnight of the first day of the month following graduation to UTC, and then subtracting 1 day.
		//I am assuming mktime will wrap month 12 back around to month 1??
		$DepartureDateUTC = mktime(0, 0, 0, $GraduationMonth + 1, 1, $AdmitYear + 4) - 86400;
		$StartDateUTC = mktime(0, 0, 0, (int)$CSVFields[$AdmitMonthIndex], 1, $AdmitYear);
		//The UserType was already read from the form fields, and is the same for all uploaded users in this batch.

		//Gender
		$Errors |= ValidateField("Gender", $CSVFields[$GenderIndex], "MFOmfo", 0, 1);
		if ($CSVFields[$GenderIndex] == "M")
			$Gender = "M";
		else if ($CSVFields[$GenderIndex] == "F")
			$Gender = "F";
		else {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unknown gender.</DIV>";
			$Errors = TRUE;
		}

		//Email.
		$Errors |= ValidateField("Official Email", $CSVFields[$EmailIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_~+.@", 0, 80);

		//The NetID is now in a seperate field.
		$Errors |= ValidateField("NetID", $CSVFields[$NetIDIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890", 0, 20);

		//Degree Major
		$Errors |= ValidateField("Major", $CSVFields[$MajorIndex], "1234567890", 0, 3);

		//Degree Track
		$Errors |= ValidateField("Degree Track", $CSVFields[$TrackIndex], "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 0, 5);
		//These are in the SOE exported CSV file as A=CE, and B=EE. Other majors have other codes.
		// Don't print an error if there is no track - this is common for most other programs.
		if ($CSVFields[$TrackIndex] == "A")
			$Track = "A";
		else if ($CSVFields[$TrackIndex] == "B")
			$Track = "B";
		else
			$Track = "";

		//Street Address
		$Errors |= ValidateField("Street 1", $CSVFields[$Street1Index], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#/-'`:.,()? ", 0, 50);
		$Errors |= ValidateField("Street 2", $CSVFields[$Street2Index], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#/-'`:.,()? ", 0, 50);
		$Street1 = ucwords(strtolower($CSVFields[$Street1Index]), "'- ");
		$Street2 = ucwords(strtolower($CSVFields[$Street2Index]), "'- ");

		//City
		$Errors |= ValidateField("City", $CSVFields[$CityIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/-'`.,()? ", 0, 50);
		$City = ucwords(strtolower($CSVFields[$CityIndex]), "'- ");

		//State
		$Errors |= ValidateField("State", $CSVFields[$StateIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-., ", 0, 30);

		//ZIP - The ZIP is all messed up in the CSV file. Leading zeros are missing, but not on the 5 digit zip codes.
		$Errors |= ValidateField("ZIP Code", $CSVFields[$ZipIndex], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-., ", 0, 20);
		$ZIPCode = "";
		$ZipLength = strlen($CSVFields[$ZipIndex]);
		//Fix all the zip code issues. For international addresses, the zip code could be any length, but the state is always blank.
		if ( ($ZipLength == 5)  || ($CSVFields[$StateIndex] == "") || strpos($CSVFields[$StateIndex], "-") )
			$ZIPCode = $CSVFields[$ZipIndex];
		else if ($ZipLength == 4)
			$ZIPCode = "0".$CSVFields[$ZipIndex];
		else if ($ZipLength == 8)
			$ZIPCode = "0".substr($CSVFields[$ZipIndex],0, 4)."-".substr($CSVFields[$ZipIndex], -4);
		else if ($ZipLength == 9)
			$ZIPCode = substr($CSVFields[$ZipIndex],0, 5)."-".substr($CSVFields[$ZipIndex], -4);
		else if ($ZipLength != 0){
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Unexpected ZIP code length: ".$ZipLength."</DIV>";
			$Errors = TRUE;
		}

		//Country. There is no country column, but can be deduced. State is blank if it is not a US address.
		if ($CSVFields[$StateIndex] != "")
			$Country = "US";
		else
			$Country = "";


		//Phone
		$Errors |= ValidateField("Phone", $CSVFields[$Phone1Index], "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789()-., ", 0, 50);


		//If any errors were found with this student print their name so the user knows who to go and fix.
		if ($Errors) {
			echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Student ".$CSVFields[$FirstNameIndex]." ".$CSVFields[$LastNameIndex]." with NetID ".$CSVFields[$NetIDIndex]." had errors, so none of their data was saved.</DIV>";
			$StudentErrors++;
			continue;
		}

		//We have to try and figure out if this student is already in the database.
		// Either the NetID or the RUID must match.
		// The check for blank NetID and RUID is to prevent a match on an existing user that somehow has both NetID and RUID blank.
		$SQLQuery = "SELECT Users.UID, Student.NetID, Student.RUID FROM Users
			LEFT JOIN
				(SELECT UID,
					(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
					(SELECT Value FROM UserRecords WHERE Field='RUID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS RUID
				FROM Users) AS Student
			ON Users.UID=Student.UID
			WHERE (Student.NetID='".$CSVFields[$NetIDIndex]."' AND Student.NetID<>'') OR (Student.RUID='".$CSVFields[$RUIDIndex]."' AND Student.RUID<>'');";
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
			//Grab the UID of the user that we are processing.
			$Fields = $SQLResult->fetch_row();
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

			//If their graduation date has passed, should I push the date ahead another semester???
			//There will be giant mess if someone accidentally uplods a stale user file!

			echo "Existing Student Found: ".$FirstName." ".$LastName.", NetID: ".$CSVFields[$NetIDIndex].", Grad: ".date("F j, Y", $DepartureDateUTC).", ZIP: ".$ZIPCode."<BR>";
			$StudentsFound++;
		}
		else {

			//New student - but only save ECE majors.
			if ($CSVFields[$MajorIndex] != "332") {
				continue;
			}

			echo "New Student Created: ".$FirstName." ".$LastName.", NetID: ".$CSVFields[$NetIDIndex].", Grad: ".date("F j, Y", $DepartureDateUTC).", ZIP: ".$ZIPCode."<BR>";

			//If in preview mode, skip the rest..
			if ($PreviewCheck == "checked") {
				$StudentsCreated++;
				continue;
			}

			//Add this user to the Users table with fields that either don't change or don't need to be logged.
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
				echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Error getting UID of new student: ".$mysqli->error."</DIV>";
				$StudentErrors++;
				continue;
			}
			$Fields = $SQLResult->fetch_row();
			$UserBeingUpdatedUID = $Fields[0];
			$StudentsCreated++;
		}

		//Skip the updating if we're in preview mode.
		if ($PreviewCheck == "checked") {
			$StudentsUpdated++;
			continue;
		}


//SHOULDN'T THIS STILL BE UpdateField()!!!???

		//Save all the fields. 
		$ChangeFlag = FALSE;
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "RUID", $CSVFields[$RUIDIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Street1", $Street1);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Street2", $Street2);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "City", $City);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "State", $CSVFields[$StateIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Zip", $ZIPCode);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Country", $Country);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Phone1", $CSVFields[$Phone1Index]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "UserStatus", $UserStatus);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Gender", $Gender);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "OfficialEmail", $CSVFields[$EmailIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "PreferredEmail", $CSVFields[$EmailIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "NetID", $CSVFields[$NetIDIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Major", $CSVFields[$MajorIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "Track", $CSVFields[$TrackIndex]);
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "StudentType", "U");
		$ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "AccessRole", "D");

		//These fields should only be updated if the CSV shadow copies have been changed in the CSV.
		if ($ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVFirstName", $CSVFields[$FirstNameIndex]))
			SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "FirstName", $FirstName);
		if ($ChangeFlag |= SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVLastName", $CSVFields[$LastNameIndex]))
			SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "LastName", $LastName);
		if ($ChangeFlag |= (SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVAdmitYear", $CSVFields[$AdmitYearIndex])
			| SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVAdmitMonth", $CSVFields[$AdmitMonthIndex])
			| SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "CSVGradMonth", $CSVFields[$GradMonthIndex]) )
		) {
			SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "DepartureDateUTC", $DepartureDateUTC);
			SaveField($UserBeingUpdatedUID, $UserDoingUpdateUID, $CurrentTime, "StartDateUTC", $StartDateUTC);
		}

		//If something was changed, then log another updated student.
		if ($ChangeFlag)
			$StudentsUpdated++;
	}


	//At this point, all the existing users have been updated and new users created, but it's not over yet.
	// Any active users in the table that weren't found in the CSV file need to be handled.
	//SELECT Users.UID, Users.MissingFlag, Students.Type, Students.DepartureDateUTC FROM Users LEFT JOIN (SELECT UID, (SELECT Value FROM UserRecords WHERE Field='StudentType' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type, (SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND UID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC FROM Users) AS Students ON Users.UID=Students.UID WHERE Users.MissingFlag='T' AND Students.Type='U';
	$SQLQuery = "SELECT Users.UID, Users.MissingFlag, Student.Type, Student.Status, Student.FirstName, Student.LastName, Student.NetID, Student.DepartureDateUTC FROM Users
		LEFT JOIN
			(SELECT UID,
				(SELECT Value FROM UserRecords WHERE Field='StudentType' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Type,
				(SELECT Value FROM UserRecords WHERE Field='UserStatus' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS Status,
				(SELECT Value FROM UserRecords WHERE Field='FirstName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS FirstName,
				(SELECT Value FROM UserRecords WHERE Field='LastName' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS LastName,
				(SELECT Value FROM UserRecords WHERE Field='NetID' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS NetID,
				(SELECT Value FROM UserRecords WHERE Field='DepartureDateUTC' AND ID=Users.UID ORDER BY CreateTime DESC LIMIT 1) AS DepartureDateUTC
			FROM Users) AS Student
		ON Users.UID=Student.UID
		WHERE Users.MissingFlag='T' AND Student.Status='A' AND Student.Type='U';";
	$SQLResult = $mysqli->query($SQLQuery);
	if ($mysqli->error) {
		WriteLog("SQL error ".$mysqli->error." on query ".$SQLQuery);
		echo "<DIV CLASS=\"alert alert-danger\" ROLE=\"alert\">Failed to get active students with missing flag set: ".$mysqli->error."</DIV>";
		goto CloseFile;
	}

	$StudentsMissing = 0;

	//If no missing students were found, we are done.
	if (mysqli_num_rows($SQLResult) != 0) {

		echo "<DIV CLASS=\"alert alert-warning\" ROLE=\"alert\">Students Missing:<BR>\r\n";

		//Go through all the active students that are missing from the CSV file and figure out what to do with them.
		//At a minimum, the missing flag has to be set back to false.
		while ($Fields = $SQLResult->fetch_assoc() ) {

			//Update the status by creating a new status field record..
			if ($PreviewCheck != "checked")
				UpdateField($Fields["UID"], $UserDoingUpdateUID, $CurrentTime, "UserStatus", "G");

			echo $Fields["FirstName"]." ".$Fields["LastName"].", NetID: ".$Fields["NetID"].", Grad: ".date("F j, Y", $Fields["DepartureDateUTC"])."<BR>";

			//Leave the Missing flag set, in case we want to go back and see who was missing in the upload.
			$StudentsMissing++;
		}

		echo "</DIV>\r\n";
	}


	//Import report.
	$StudentsUpdated -= $StudentsCreated;
	echo "<DIV CLASS=\"alert alert-success\" ROLE=\"alert\">";
	if ($PreviewCheck == "checked")
		echo "<STRONG>PREVIEW MODE - DATABASE NOT CHANGED</STRONG><BR>";
	echo "Students read in from CSV file \"".$FileName."\": ".$StudentsRead."<BR>";
	echo "Existing students found: ".$StudentsFound."<BR>";
	echo "New students created in database: ".$StudentsCreated." <BR>";
	echo "Existing students updated: ".$StudentsUpdated."<BR>";
	echo "Students with import errors that were not processed: ".$StudentErrors."<BR>";
//	echo "Students missing from CSV file that have been changed to graduated status:".$StudentsGraduated."<BR>";
	echo "Active students missing from CSV file: ".$StudentsMissing."<BR>";
//	echo "Errors handling missing students:".$MissingErrors;
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
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersUploadUgrads.log"); 
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


 	//Stores the field to a name/value record in the change record table.
	//Does nothing if the field already exists, regardless of value (could be blank).
	function SaveField($UserBeingUpdated, $UserDoingUpdate, $TimeStamp, $FieldName, $FieldValue) {

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

		//New field blank, Old field exists -> Do nothing
		//New field blank, Old field not exists -> Do nothing
		//New field has text, Old field exists -> Do nothing
		//New field has text, Old field not exists -> Write a new record

		if ( (strlen($FieldValue) == 0) || ($SQLResult->num_rows == 1) )
			return FALSE;

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

<!--

To backup SQL tables:

CREATE TABLE new_table LIKE original_table;
INSERT INTO new_table SELECT * FROM original_table;

This should copy all the table structure, auto-increment value, indexing, and data.

-->
