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
    $NetID = $_SESSION['netid'];


	//This has to go out after the user is authenticated and their role is determined, because the menus are based on role.
	require('../template/head.php');

    //Page content begins here..
    echo "<DIV ID=\"content\">";
    echo "<DIV CLASS=\"container\">";

	//There is intentionally no authenticaion here, becuase this student is not already in the system.

 
    //Array of names for user status dropdown list box
    $StatusList = array
      (
      ""=>"",
      "A"=>"Active",
      "G"=>"Graduated",
      "T"=>"Transferred",
      "S"=>"Suspended",
      "E"=>"Expelled",
      "B"=>"Sabbatical",
      "R"=>"Retired",
      "F"=>"Terminated",
      "X"=>"Deleted",
      "M"=>"Missing",
      "D"=>"Deceased"
      );

    $StudentTypeList = array
      (
      ""=>"",
      "U"=>"Undergraduate",
      "M"=>"MS With Thesis",
      "W"=>"MS Without Thesis",
      "P"=>"PhD",
      "N"=>"Non-Matriculated"
      );

     $TrackList = array
      (
      ""=>"",
      "A"=>"Computer Engineering",
      "B"=>"Electrical Engineering"
      );

     $GenderList = array
      (
      ""=>"",
      "M"=>"Male",
      "F"=>"Female",
      "Z"=>"Other"
      );

    $NetID = "";
    $RUID = "";
    $UserStatus = "A";
    $AccessRole = "D";
    $StudentType = "U";
	$FirstName = "";
	$MiddleName = "";
	$LastName = "";
    $Gender = "";
    $PreferredEmail = "";
    $Phone1 = "";
    $Major = "";
    $Track = "";
    $DepartureDate = "";


    echo "<FORM METHOD=\"POST\" ACTION=\"UsersStudentProcessNew.php\">";

    echo "<P>";
    echo "Please complete the following form to create an account for yourself on ECEAPPs. Most ECE students should already be in the system, but for various reasons some might be missing. After your account is created, you will have access to review/update your information, and create a new SPN request.";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Student Type</STRONG><BR>"; DropdownListBox("studenttype", $StudentTypeList, $StudentType); echo "<BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>RUID</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"ruid\" SIZE=\"9\"  MAXLENGTH=\"9\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>First name</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"firstname\"  SIZE=\"20\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Middle name</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"middlename\" SIZE=\"20\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "<STRONG>Last name</STRONG>  <BR>\r\n<INPUT TYPE=\"text\" NAME=\"lastname\"   SIZE=\"20\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
	echo "<STRONG>Gender</STRONG><BR>"; DropdownListBox("gender", $GenderList, $Gender); echo "<BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Email Address</STRONG><BR>\r\n<INPUT TYPE=\"text\" NAME=\"preferredemail\" SIZE=\"40\"  MAXLENGTH=\"80\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Phone</STRONG> <BR>\r\n<INPUT TYPE=\"text\" NAME=\"phone1\" \"SIZE=\"15\" MAXLENGTH=\"50\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Major</STRONG><BR>Your 3-digit major code, such as 332 for electrical/computer engineering, 650 for mechanical/aerospace, 198 for computer science, or 440 for undeclared engineering majors. Your major code is the MMM portion of your course numbers: SS:MMM:CCC:RR<BR>\r\n<INPUT TYPE=\"text\" NAME=\"major\" SIZE=\"3\" MAXLENGTH=\"3\"><BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Degree Track</STRONG><BR>If you are an ECE student, please select whether you are electrical or computer engineering.<BR>"; DropdownListBox("track", $TrackList, $Track); echo "<BR>\r\n";
    echo "</P>";

    echo "<P>";
    echo "<STRONG>Expected Graduation Date (Month/Day/Year)</STRONG><BR>\r\n";
    echo "<INPUT TYPE=\"text\" NAME=\"departdate\" SIZE=\"10\"  MAXLENGTH=\"10\">\r\n";
    echo "</P>";
 
    //The create button.
    printf("<P><INPUT TYPE=\"SUBMIT\" VALUE=\"Create Student\"></P>");

    echo "</FORM>";
    echo "</DIV>";
    echo "</DIV>";

SendTailer:
    require('../template/footer.html');
    require('../template/foot.php');
?>


<?php
    //Function to write a string to the log. Getting permission denied... SE Linux issue?
    function WriteLog($LogString) {
	    error_log(date("m/d/Y h:i:sa").", ".$_SERVER['SCRIPT_NAME'].": ".$LogString."\r\n", 3, "/www/custom/eceapps/Users/UsersStudentCreateNew.log"); 
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