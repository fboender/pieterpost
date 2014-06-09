<?
/* ----------------------------------------------------------------- */
/*                                                                   */
/*  PieterPost                                                       */
/*                                                                   */
/*  PHP/web interface to POP3 mailbox                                */
/*  Low-feature, but it sure is perty ;)                             */
/*                                                                   */
/*  Copyright (C) by Ferry Boender                                   */
/*                                                                   */
/* This program is free software; you can redistribute it and/or     */
/* modify it under the terms of the GNU General Public License       */
/* as published by the Free Software Foundation; either version 2    */
/* of the License, or (at your option) any later version.            */
/*                                                                   */
/* This program is distributed in the hope that it will be useful,   */
/* but WITHOUT ANY WARRANTY; without even the implied warranty of    */
/* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the     */
/* GNU General Public License for more details.                      */
/*                                                                   */
/* You should have received a copy of the GNU General Public License */
/* along with this program; if not, write to the Free Software       */
/* Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.         */
/*                                                                   */
/* For more information, see the COPYING file supplied with this     */
/* program.                                                          */
/*                                                                   */
/* ----------------------------------------------------------------- */

// Global vars
$prefs["version"] = "%%VERSION";
$prefs["pp_homepage"] = "%%HOMEPAGE";


// Set by setup.sh script. If not, remove the REF### part, and fill in the
// values yourself
$prefs["prefdir"]="/var/pieterpost/";
$prefs["hostname"]="dev.local";
$prefs["theme"]="bluegreen";
$prefs["language"]="english";
$prefs["pop_server"]="localhost";
$prefs["pop_port"]="110";

// Default preferences
$prefs["sortbox"] = "date";

session_start();

// Load default preferences
$lang  = load_lang($prefs["prefdir"]."languages/".$prefs["language"].".lang");
$theme = load_theme ($prefs["prefdir"]."themes/".$prefs["theme"].".pt");

load_imap();
load_encodings($mime_enc, $trans_enc); /* fill mime and trans tbls */

/* Some vars are given through the GET and POST method */
$action = "";
if (isset($_REQUEST["action"])) {
	$action = $_REQUEST["action"];
}

// If logged in, overwrite the default prefs with the users prefs
if (session_is_registered("username") && 
    session_is_registered("password")) {
	$username = $_SESSION["username"];
	$password = $_SESSION["password"];
	$prefs = array_merge ($prefs, load_prefs ($prefs["prefdir"]."/".$_SESSION["username"].".prefs"));

	/* Reload languages and theme */
	$lang  = load_lang($prefs["prefdir"]."languages/".$prefs["language"].".lang");
	$theme = load_theme ($prefs["prefdir"]."themes/".$prefs["theme"].".pt");

} else {
	$username = "";
	$password = "";

	/* Yoy may only request loginget, logincheck if not logged in. */
	if (isset($action) && 
           !($action == "loginget" || $action == "logincheck" || $action == "")) {
		die ("Cannot access requested page. Perhaps your session has expired?");
	}
}

// Some actions require exceptions.
// User just came from login page? 
if ($action !== "logincheck") {
	if (!session_is_registered("username") ||
	    !session_is_registered("password")) {
		$action = "loginget";
	}
}

// Determine current action for pp
switch ($action) {
	case "logincheck" : 
		act_login_check ($_POST["frm_login"]); 
		act_mbox_view ($_GET["sort"], $_GET["filter"]); 
		break;
	case "loginget" : 
		act_login_get();
		break;
	case "logout" : 
		act_logout();
		act_login_get();
		break;
	case "maildelete" : 
		act_mail_delete($_REQUEST["del"]);
		act_mbox_view ($_GET["sort"], $_GET["filter"]);
		break;
	case "mailview" : 
		act_mail_view($_GET["msgid"], $_GET["headers"]);
		break;
	case "attachmentview" : 
		act_attachment_view($_GET["msgid"], $_GET["attachmentid"]); 
		break;
	case "mailcompose" : 
		act_mail_compose($_GET["to"], $_GET["msgid"]);
		break;
	case "mailsend" : 
		act_mail_send($_POST["frm_compose"]);
		break;
	case "preferences" :
		act_preferences($_POST["subaction"], $_POST["frm_pref"]);
		break;
	case "addressbook" : 
		act_addressbook($_POST["subaction"], $_REQUEST["frm_address"]);
		break;
	case "mboxview" : 
		act_mbox_view($_GET["sort"], $_GET["filter"]);
		break;
	default : 
		act_mbox_view($_GET["sort"], $_GET["filter"]);
		break;
}

//----------------------------------------------------------------------------
// Name   : load_lang
// Desc   : Load global, personal or default language variables
// Params : file_language = full path to location of .lang file to load
// Returns: array filled with the language strings
// Remarks: If file_language can't be loaded, the default language is used, 
//          which is english. If the language file ommits any strings, their
//          default english counterpart will be used.
//----------------------------------------------------------------------------
function load_lang($path_language) {
	if ($file_language = @file($path_language)) {
		for ($i=0; $i!=count($file_language); $i++) {
			$key = substr(
					$file_language[$i],
					0,
					strpos($file_language[$i],"=")
			);
			$value = substr(
					$file_language[$i],
					strpos($file_language[$i],"=")+1,
					strlen($file_language[$i])
			);

			$key = chop($key);
			$value = chop($value);

			$lang["$key"] = $value;
		}
	} else {
		$msg = "Can't load the language file! Contact the system admin!<br>";
		$msg .= "Is your php configuration set up properly? Does php allow access to the preference directory (check the open_basedir directive)?<br>";
		$msg .= "Aborting pieterpost. There is no use to continue without the language file :-(.";
		dialog ("Language load error", $msg, true);
		exit();
	}

	return ($lang);
}

//----------------------------------------------------------------------------
// Name   : load_imap
// Desc   : Load the imap module
// Params : settings = Default or user specific settings.
// Returns: -
// Remarks: If the imap module can't be loaded, an error is displayed.
//----------------------------------------------------------------------------
function load_imap () {
	global $lang;
	
	// Check for imap module, and try to load it if it's not already
	if (!extension_loaded("imap")) {
		@dl ("imap.so");
	}
	
	// If it's still not loaded, it must be missing. Show error and quit
	if (!extension_loaded("imap")) {
		dialog ("Imap load error", $lang["loadimap_msg_cantload"], true);
		exit();
	}
}

//----------------------------------------------------------------------------
// Name   : load_encodings
// Desc   : Sets some mime encoding information.
// Params : mime_enc  = array which will be filled with mime encode types
//          trans_enc = array which will be filled with transfer encoding tps
// Returns: -
// Remarks: Modifies the two arrays past as parameters (pass by ref)
//----------------------------------------------------------------------------
function load_encodings(&$mime_enc, &$trans_enc) {
	// Pathetic mime-encoding table, cause imap functions return # instead of 
	// names.
	$mime_enc[0] = "text";
	$mime_enc[1] = "multipart";
	$mime_enc[2] = "message";
	$mime_enc[3] = "application";
	$mime_enc[4] = "audio";
	$mime_enc[5] = "image";
	$mime_enc[6] = "video";
	$mime_enc[7] = "other";
	
	// Transfer encoding, same deal as with mime-enc table 
	$trans_enc[0] = "7BIT";
	$trans_enc[1] = "8BIT";
	$trans_enc[2] = "BINARY";
	$trans_enc[3] = "BASE64";
	$trans_enc[4] = "QUOTED-PRINTABLE";
	$trans_enc[5] = "OTHER";
}

//----------------------------------------------------------------------------
// Name   : load_prefs
// Desc   : Loads in the preferences from a pref file
// Params : path_prefs = Preferences file to read
// Returns: Array filled with the users preferences read from FILE_PREFS
// Remarks: -
//----------------------------------------------------------------------------
function load_prefs($path_prefs) {

	$prefs = array();

	if ($file_prefs = @file($path_prefs)) {
		for ($i=0; $i!=count($file_prefs); $i++) {
			$key = substr(
					$file_prefs[$i],
					0,
					strpos($file_prefs[$i],"=")
			);
			$value = substr(
					$file_prefs[$i],
					strpos($file_prefs[$i],"=")+1,
					strlen($file_prefs[$i])
			);

			$key = chop($key);
			$value = chop($value);

			$prefs[$key] = $value;
		}
		
		$prefs["signature"] = str_replace("\\n","\n",$prefs["signature"]);
	}
	
	return $prefs;
}

//----------------------------------------------------------------------------
// Name   : load_address
// Desc   : Loads in the addresses contained in FILE_ADDRESS
// Params : path_address = Address file to read
// Returns: Array filled with address information
// Remarks: -
//----------------------------------------------------------------------------
function load_address($path_address) {

	if ($file_addresses = @file($path_address)) {
		for ($i=0; $i!=count($file_addresses); $i++) {
			$address[$i][0] = substr(
				$file_addresses[$i],
				0,
				strpos($file_addresses[$i],";")
			);
			$address[$i][1] = substr(
				$file_addresses[$i],
				strpos($file_addresses[$i],";")+1,
				strlen($file_addresses[$i])
			);

			$address[$i][0] = chop($address[$i][0]);
			$address[$i][1] = chop($address[$i][1]);
		}
	}

	return $address;
}

//----------------------------------------------------------------------------
// Name   : load_theme
// Desc   : Loads in the theme information from the file FILE_THEME
// Params : path_theme = file which contains the theme information
// Returns: String which contains the theme
// Remarks: A theme string is in essence nothing more than the stylesheet
//          part of a html page.
//----------------------------------------------------------------------------
function load_theme ($path_theme) {
	if (($file_theme = @fopen ($path_theme, "r")) !== FALSE) {
		$contents = fread($file_theme, filesize($path_theme));
		fclose($file_theme);
	}

	return $contents;
}

//----------------------------------------------------------------------------
// Name   : load_avail_languages
// Desc   : Scan the language directory so we know which languages are
//          available
// Params : path_languages = The language directory
// Returns: Array in which each element is a language which is available
// Remarks: an available language is simply a part of the filename found in
//          DIR_LANGUAGES
//----------------------------------------------------------------------------
function load_avail_languages ($path_languages) {

	if ($dir_languages = opendir ($path_languages)) {
		while (($filename = readdir($dir_languages)) !== false) {
			if ($filename != "." & $filename != "..") {
				$thislang = explode(".",$filename);
				$languages[] = $thislang[0];
			}
		}
		closedir ($dir_languages);
	}
	return ($languages);
}

//----------------------------------------------------------------------------
// Name   : load_avail_themes
// Desc   : Scan the theme directory so we know which languages are available
// Params : path_themes = The theme directory
// Returns: Array in which each element is a theme which is available
// Remarks: an available theme is simply a part of the filename found in
//          DIR_THEMES
//----------------------------------------------------------------------------
function load_avail_themes ($path_themes) {

	if ($dir_themes = opendir ($path_themes)) {
		while (($filename = readdir($dir_themes)) !== false) {
			if ($filename != "." & $filename != "..") {
				$thistheme = explode (".", $filename);
				$themes[] = $thistheme[0];
			}
		}
		closedir ($dir_themes);
	}
	return ($themes);
}


//----------------------------------------------------------------------------
// Name   : header_menu
// Desc   : Output html code for the navigation menu
// Params : lang  = The array contianing the current language
//          title = Title to show as current page
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function header_menu ($title) {
	global $lang;
	?>
	<table width="100%" cellspacing="0" cellpadding="0" border="0">	
	<tr>
	<td class="menu_background">
		<a class="menu_link" href="pp.php?">
			<?=$lang["menu_lnk_mboxview"]?>
		</a> | 
		<a class="menu_link" href="pp.php?action=mailcompose">
			<?=$lang["menu_lnk_compose"]?>
		</a> | 
		<a class="menu_link" href="pp.php?action=preferences">
			<?=$lang["menu_lnk_prefs"]?>
		</a> | 
		<a class="menu_link" href="pp.php?action=addressbook">
			<?=$lang["menu_lnk_adrbook"]?>
		</a> | 
		<a class="menu_link" href="pp.php?action=logout">
			<?=$lang["menu_lnk_logout"]?>
		</a>
	</td>
	<td class="menu_background" align="right">
		<b class="menu_indicator">
		<?=@$title?>
		</b>
	</td>
	</tr>
	
	<tr>
	<td class="menu_foreground" colspan="2"><img width="1" height="1" alt=""></td>
	</tr>
	</table>
	<br>
	<?
}

//----------------------------------------------------------------------------
// Name   : header_html
// Desc   : Outputs a html header including the stylesheet which is the theme
//          (or the default theme)
// Params : title = Title to show for this page in the browser window
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function header_html ($title) {
	global $prefs, $theme;

	header ("Pragma: no-cache");
	?>
	<html>
	<head>
		<?
		// Show the user's prefered theme or the system wide theme
		if ($theme) {
			echo $theme;
		} else {
			// Default theme for when no theme could be loaded at all
			?>
			<style type="text/css">
				*                   { font-size      : 12px;
				                      font-family    : verdana;    }
				
				a		            { text-decoration: none;       }
				a:link              { color          : #CD9900;	   }
				a:visited           { color          : #CD9900;    }
				a:hover             { color          : #DDA910;    }
				a:active            { color          : #DDA910;    }
				
				td.menu_background  { background     : #292c29;    }
				td.menu_foreground  { background     : #e6a100;    }
				 b.menu_indicator   { color          : #f6ba00;    }
				 a.menu_link        { color          : #ff8100;    }

				 a.iface_header_fg  { color          : #FFFFFF;    }
				 b.iface_header_fg  { color          : #FFFFFF;    }
				td.iface_header_bg  { background     : #9c6d00;    }
				tr.iface_header_bg  { background     : #9c6d00;    }
				td.iface_main_bg_hi { background     : #949194;    }
				tr.iface_main_bg_hi { background     : #949194;    }
				td.iface_main_bg    { background     : #7b7a7c;    }
				tr.iface_main_bg    { background     : #7b7a7c;    }
				font.iface_main_fg  { color          : #000000;    }
				 b.iface_main_fg    { color          : #3a2600     }
				 a.iface_main_fg    { color          : #3a2600;    }

				input               { border-style   : solid; 
				                      border-width   : 1px; 
				                      border-color   : #000000;    }
				textarea            { border-style   : solid; 
				                      border-width   : 1px; 
				                      border-color   : #000000;    }
				select              { border-style   : solid; 
				                      border-width   : 1px; 
				                      border-color   : #000000;    }
				body.iface_main     { color          : #FFFFFF; 
				                      background     : #000000;    
				                      margin         : 0px 0px 0px 0px; }
			</style>
			<?
		}
		?>
		<title>
			PieterPost - <?=$title?>
		</title>
	</head>
	
	<body class="iface_main" marginheight="0" marginwidth="0">
	<?
}

//----------------------------------------------------------------------------
// Name   : footer_html
// Desc   : Outputs a default html footer (including the copyright notice and
//          version/link to the homepage
// Params : -
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function footer_html () {
	global $lang, $prefs;

	?>
		<br>
		<table width="100%" cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td class="menu_foreground"><img width="1" height="1" alt=""></td>
		</tr>
		<tr>
			<td>
				<a href="<?=$prefs["pp_homepage"]?>"><b>PieterPost <?=$prefs["version"]?></b></a> 
				, <?=$lang["footerhtml_lbl_by"]?> Ferry Boender, &copy; 2000-2004, 
				<?=$lang["footerhtml_lbl_released"]?> 
				<a href="http://www.gnu.org/copyleft/gpl.html">GPL.</a>
				&nbsp; &nbsp; Found a <a href="http://projects.electricmonk.nl/index.php?action=BugList&project_id=1">bug</a>?
			</td>
		</tr>
		</table>
		<?
		/* Automatically focus the first visible form field */
		?>
		<script language="javascript">
			var i = 0;
			if (document.forms[0]) {
				while (document.forms[0].elements[i]) {
					if (document.forms[0].elements[i].type != "hidden") {
						document.forms[0].elements[i].focus();
						break;
					}
					i++;
				}
			}
		</script>
	</body>
	</html>
	<?
}


//----------------------------------------------------------------------------
// Name   : dialog
// Desc   : Shows a html dialog with a message
// Params : title = title to show in the dialog
//          msg = html formatted msg
//          headers = false = no headers, true = headers
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function dialog ($title, $msg, $headers = false) {
	if ($headers == true) {
		header_html ($title);
	}
	?>
	<br><br>
	<center>
	
	<table cellspacing="0" cellpadding="5" border="0">
		<tr>
		<td colspan="2" align="center" class="iface_header_bg">
			<b class="iface_header_fg"><?=$title?></b>
		</td>
		</tr>
		<tr>
		<td class="iface_main_bg"> 
			<font class="iface_main_fg"><?=$msg?></div>
		</td>
		</tr>
	</table>
	</center>
	<?
	if ($headers == true) {
		footer_html();
	}
}

//----------------------------------------------------------------------------
// Name   : act_logout
// Desc   : Logs the user out by destroying the session and displays an message
//          or goes back to the login page (depending on settings)
// Params : -
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function act_logout () {
	session_destroy ();
}

//----------------------------------------------------------------------------
// Name   : act_login_get
// Desc   : Shows the form in which the user can enter his login information
// Params : -
// Returns: -
// Remarks: Since the user isn't logged in yet, the system wide theme and 
//          language are used here.
//----------------------------------------------------------------------------
function act_login_get () {
	global $lang, $prefs;

	header_html ($lang["title_login"]);
	?>
	<br><br>
	<center>
	
	<table cellspacing="0" cellpadding="2" border="0">
		<tr>
		<td colspan="2" align="center" class="iface_header_bg">
			<b class="iface_header_fg"><?=$lang["title_login"]?></b>
		</td>
		</tr>
		
		<form action="pp.php" method="post">

		<tr>
		<td class="iface_main_bg"> 
			&nbsp; <b class="iface_main_fg"><?=$lang["loginget_lbl_username"]?></b> :
		</td>
		<td class="iface_main_bg">
			<input type="text" name="frm_login[username]" size="15"> &nbsp; 
		</td>
		</tr>

		<tr>
		<td class="iface_main_bg">
			&nbsp; <b class="iface_main_fg"><?=$lang["loginget_lbl_password"]?></b> :
		</td>
		<td class="iface_main_bg">
			<input type="password" name="frm_login[password]" size="15"> &nbsp;
		</td>
		</tr>

		<tr>
		<td class="iface_main_bg">
			&nbsp;
		</td>
		<td class="iface_main_bg">
                       <input type="hidden" name="action" value="logincheck">
                       <input type="submit" name="doaction" value="<?=$lang["loginget_btn_login"]?>" size="15"> &nbsp;
		</td>
		</tr>

		</form>
	</table>
	</center>
	<br>
	<?
	footer_html();
}

//----------------------------------------------------------------------------
// Name   : act_login_check
// Desc   : Verifies the information user entered in act_login_get, and if 
//          it checks out it logs the user in by setting some session vars
// Params : frm_login = associative array containing the login information.
//                      filled by act_login_check.
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function act_login_check ($frm_login) {
	global $username, $password, $lang, $prefs, $theme, $_SESSION;
	
	// Open connection to pop3 to validate un + pw
	if (@$mbox = imap_open("{".$prefs["pop_server"]."/pop3:".$prefs["pop_port"]."}INBOX",$frm_login["username"],$frm_login["password"])) {
		imap_close ($mbox);	

		// UN + pw are valid, 'log' user in.
		session_register ("username");
		session_register ("password");

		$_SESSION["username"] = strtolower($frm_login["username"]);
		$_SESSION["password"] = $frm_login["password"];
		
		/* Set these so the next called function after login (act_mailbox_view)
		   knows about them */
		$username = $_SESSION["username"];
		$password = $_SESSION["password"];
		
		// Load the user's settings
		$prefs = array_merge ($prefs, load_prefs ($prefs["prefdir"]."/".$frm_login["username"].".prefs"));
		$lang  = load_lang($prefs["prefdir"]."languages/".$prefs["language"].".lang");
		$theme = load_theme ($prefs["prefdir"]."themes/".$prefs["theme"].".pt");
	
	} else {
		// Check error message
		// This used to work, but now it seems that it will not tell when
		// a pop server simply isn't available at the host.
		if (stristr(imap_last_error(),"connection refused"))  {
			// No connection to pop3 daemon
			dialog ("Connection error", $lang["logincheck_msg_nopop3"], true);
		} else {
			// UN + pw are not valid, show error
			dialog ("Login error", $lang["logincheck_msg_authfailed"], true);
		}
		exit();
	}
}

//----------------------------------------------------------------------------
// Name   : act_mbox_view
// Desc   : Shows the users email box, containing the email messages. 
// Params : sort   = How to sort the mailbox. This overrides default preference
//          filter = Which messages to show in the mailbox (new, old,etc).
//                   this overrides the default preference.
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function act_mbox_view ($sort="", $filter="") {
	global $username, $password, $lang, $prefs;
	
	// Runtime selected sort overwrites the preferences set sort
	if ($sort == "") { 
		$sort = $prefs["sort"]; 
	}

	// Runtime selected filter overwrites the preferences set filter
	if ($filter == "") { 
		if ($prefs["filter"]) {
			$filter = $prefs["filter"]; 
		} else {
			$filter = "all";
		}
	}
	
	$mbox = @imap_open("{".$prefs["pop_server"]."/pop3:".$prefs["pop_port"]."}INBOX",$username, $password);
	$mbox_num_msg = imap_num_msg($mbox);
				
	// Load 'seen' message list
	$file_seen = @file($prefs["prefdir"].$username.".msg");

	header_html($lang["title_mboxview"]);
	header_menu($lang["title_mboxview"]);
	?>
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td width="1%">
			<nobr>
			&nbsp;
			<b>
				<?=$lang["mboxview_lbl_nrofmails"]?>
			</b>
			&nbsp;
			</nobr>
		</td>
		<td class="iface_main_bg_hi" width="1%">
			<nobr>
			&nbsp;
			<font class="iface_main_fg">
				<?=$mbox_num_msg ?>
			</font>
			&nbsp;
			</nobr>
		</td>
			<form action="pp.php" method="GET">
		<td align="right">
			<?=$lang["mboxview_lbl_show"]?>
			<select name="filter" OnChange="this.form.submit()">
					<option value="all" <? if ($filter=='all') { echo "selected"; } ?>><?=$lang["mboxview_lbl_all"]?>
					<option value="new" <? if ($filter=='new') { echo "selected"; } ?>><?=$lang["mboxview_lbl_new"]?>
					<option value="old" <? if ($filter=='old') { echo "selected"; } ?>><?=$lang["mboxview_lbl_old"]?>
			</select>
			<input type="submit" value="<?=$lang["mboxview_btn_show"]?>">
		</td>
		</form>
	</tr>
	</table>
	<br>
	
	<?
	// Mailbox sorting headers 
	?>
	<form action="pp.php" method="post">
		<table width="100%" cellspacing="0" cellpadding="0" border="0">

		<tr class="iface_header_bg">
		<td>&nbsp;<b>
			<a href="pp.php?sort=subj" class="iface_header_fg">
			<?=$lang["mboxview_lnk_sort_del"]?>
			</a>
			</b>
		</td>
		<td>
			&nbsp;<b>
			<a href="pp.php?sort=subj" class="iface_header_fg">
			<?=$lang["mboxview_lnk_sort_subject"]?>
			</a>
			</b>
		</td>
		<td colspan="2">&nbsp;<b>
			<a href="pp.php?sort=from" class="iface_header_fg">
			<?=$lang["mboxview_lnk_sort_from"]?>
			</a>
			</b>
		</td>
		<td>&nbsp;<b>
			<a href="pp.php?sort=date" class="iface_header_fg">
			<?=$lang["mboxview_lnk_sort_date"]?>
			</a>
			</b>
		</td>
		</tr>
		<?
			switch ($sort) {
				case "date" : $mbox_sorted = imap_sort($mbox, SORTDATE, 1); break;
				case "from" : $mbox_sorted = imap_sort($mbox, SORTFROM, 0); break;
				case "subj" : $mbox_sorted = imap_sort($mbox, SORTSUBJECT, 0); break;
				default     : $mbox_sorted = imap_sort($mbox, SORTDATE, 1); break;
			}
	
			// Process all headers retrieved from mailbox
			for ($i=0; $i!=$mbox_num_msg; $i++) {
				
				$mbox_header = imap_header ($mbox, $mbox_sorted[$i]);
			
				@$date = $mbox_header->udate;
				@$from_name = $mbox_header->from[0]->personal;
				@$from_mail = $mbox_header->from[0]->mailbox."@".$mbox_header->from[0]->host;
				@$subject = htmlentities($mbox_header->subject);
			
				// There needs to be a subject, so the user can click it.
				if ($subject=="") { $subject = $lang["mboxview_lbl_nosubject"]; }
				
				// See if this message had been read before
				$show = TRUE;  /* If the message should be shown */
				$seen = FALSE; /* has the message been seen? */
				$seen_class = "class=\"iface_main_bg_hi\"";
				for ($s=0; $s!=count($file_seen); $s++) {
					if ($file_seen[$s]==$mbox_header->message_id."\n") {
						/* Do not show already viewed msgs if only new should be shown */
						$seen_class = "class=\"iface_main_bg\"";
						$seen = TRUE;
					}
				}

				if ($seen) {
					$nrofmsgs_old++;
					if ($filter == "new") {
						$show = FALSE;
					}
					// Add this msg to the new seen msgs file, so old sees msgs
					// which are no longer available are removed
					$file_seen_new[] = $mbox_header->message_id;
				} else {
					$nrofmsgs_new++;
					if ($filter == "old") {
						$show = FALSE;
					}
				}
			
				if ($show == TRUE) {
					?>
					<tr <?=$seen_class?> >
					<td valign="top">
						&nbsp;
						<input 
							type="checkbox" 
							name="del[<?=$i?>]"
							value="<?=$mbox_header->message_id?>">
					</td>
					<td valign="top">
						&nbsp;
						<a 
							class="iface_main_fg" 
							href="pp.php?action=mailview&msgid=<?=$mbox_sorted[$i]?>"><?=$subject?></a>
					</td>
					<td valign="top">
						&nbsp;
						<font class="iface_main_fg">
							<?=$from_name?>
						</font>
					</td>
					<td valign="top">
						&nbsp;
						<font class="iface_main_fg">
							(
							<a class="iface_main_fg" href="pp.php?action=mailcompose&to=<?=$from_mail?>"><?=$from_mail?></a>
							)
						</font>
					</td>
					<td valign="top">
						<nobr>
							&nbsp;
							<font class="iface_main_fg">
								<?=date ("d M Y", $date)?>
							</font>
						</nobr>
					</td>
					</tr>

					<tr><td colspan="5"><img height="1" width="1" alt=""></td></tr>
					<?	
				}
			}	
			
			if ( ($filter == "new" && $nrofmsgs_new == 0) || 
			     ($filter == "old" && $nrofmsgs_old == 0) ||
				 ($filter == "all" && $nrofmsgs_new + $nrofmsgs_old == 0)) {
					?>
					<tr class="iface_main_bg">
					<td colspan="5">
						<br>
						<font class="iface_main_fg">
						&nbsp; 
					<?
			}
			
			if ($filter == "new" && $nrofmsgs_new == 0) {
				echo $lang["mboxview_msg_nonewmail"];
			} else
			if ($filter == "old" && $nrofmsgs_old == 0) {
				echo $lang["mboxview_msg_nooldmail"];
			} else 
			if ($filter == "all" && $nrofmsgs_new + $nrofmsgs_old == 0) {
				echo $lang["mboxview_msg_nomail"];
			}

			if ( ($filter == "new" && $nrofmsgs_new == 0) || 
			     ($filter == "old" && $nrofmsgs_old == 0) ||
				 ($filter == "all" && $nrofmsgs_new + $nrofmsgs_old == 0)) {
					?>
					<br><br>
					</td>
					</tr>
					<?
			}
					
			// Create the new 'seen' messages file
			if ($file_seen_new) {
				if ($f_seenmsg = fopen($prefs["prefdir"].$username.".msg","w")) {
					foreach ($file_seen_new as $value) {
						fputs ($f_seenmsg, $value."\n");
					}
					fclose ($f_seenmsg);
				}
			} else {
				// No messages are currently tagged as 'seen', so create an empty file
				if (@$f_seenmsg = fopen($prefs["prefdir"].$username.".msg","w")) {
					fclose ($f_seenmsg);
				} else {
					?>
					<script language="javascript">
						alert ("Pieterpost couldn't open your preferences file for writing\nYour settings will not be saved, nor will Pieterpost be able to mark any mail you've already read");
					</script>
					<?
				}
			}
		
			imap_close ($mbox);
		?>
		</table>
		<?
			// If there's any mail, show extra action buttons.
			if (!(($filter == "new" && $nrofmsgs_new == 0) || 
			     ($filter == "old" && $nrofmsgs_old == 0) ||
				 ($filter == "all" && $nrofmsgs_new + $nrofmsgs_old == 0))) {
				?>
				<input type="hidden" name="msgs" value="<?=$mbox_num_msg?>">
                               <input type="hidden" name="action" value="maildelete">
                               <input type="submit" name="tmpaction" value="<?=$lang["mboxview_btn_delete"]?>"></font><br>
				<?
			}
		?>
	</form>
	<?
	footer_html();
	
}

//----------------------------------------------------------------------------
// Name   : mailpart_parse
// Desc   : Parses a (mime) part of a message and returns all viewable text as
//          html.
// Params : structure = The structure of the current part as returned by imap_fetchstructure
//          mbox    = mailbox handler to use (by imap_open)
//          msgid   = The message id of the message to parse
//			part    = The part of the message to parse
// Returns: A string containing all viewable taxt in the message part as html.
// Remarks: - Recursive if multipart mime.
//          - Transforms non-html text into html (clickable url's, etc)
//----------------------------------------------------------------------------
function mailpart_parse ($structure, $mbox, $msgid, $part) {
	global $mime_enc, $trans_enc;
	
	if ($mime_enc[$structure->type] == "multipart") {
		// Recursively parse all parts of this multipart message
		for ($i = 0; $i < count($structure->parts); $i++) {
			$text .= mailpart_parse ($structure->parts[$i], $mbox, $msgid, $part.".".($i+1));
		}
	} else {
		// Fetch and transform this part's body to viewable html
		$this_text = imap_fetchbody($mbox, $msgid, $part);

		if ($trans_enc[$structure->encoding] == "BASE64") {
			$this_text = imap_base64($this_text);
		} else
		if ($trans_enc[$structure->encoding] == "QUOTED-PRINTABLE") {
			$this_text = imap_qprint($this_text);
		}

		if ($mime_enc[$structure->type] == "text") {
			if (strtoupper($structure->subtype) == "PLAIN") {
				// Content-type: text/plain: transform to html

				$this_text = htmlentities($this_text, ENT_NOQUOTES);

				// URL's and Email addresses 
				$this_text = preg_replace ("|([a-z0-9]?)*://([a-z0-9_\-\./\?\&=;]?)*|i", "<a class=\"iface_main_fg\" href=\"$0\" target=\"_blank\">$0</a>", $this_text);
				$this_text = preg_replace ("|([a-z0-9_\-\.]?)*@([a-z0-9_-]?)*.([a-z0-9_-]?)*|i", "<a class=\"iface_main_fg\" href=\"pp.php?action=addressbook&frm_address[email]=$0\">$0</a>", $this_text);

				$this_text = nl2br($this_text);
			} else 
			if (strtoupper($structure->subtype) == "HTML") {
				;
			} else {
				/* Not plaintext or html? Do now show */
				$this_text = ""; 
			}
		}

		$text = $this_text;
	}

	return ($text);
}

//----------------------------------------------------------------------------
// Name   : act_mail_view
// Desc   : Shows the contents of an email message
// Params : msgid   = The message id of the message to show
//          headers = Show headers as short or full.
// Returns: -
// Remarks: 
//----------------------------------------------------------------------------
function act_mail_view ($msgid, $headers) {
	global $username, $password, $lang, $prefs, $mime_enc, $trans_enc;

	// Alternate rowcolors 
	function rowstyle () {
		global $rowstyle;

		if ($rowstyle == "iface_main_bg") {
			$rowstyle = "iface_main_bg_hi";
		} else {
			$rowstyle = "iface_main_bg";
		}

		return ($rowstyle);
	}

	header_html("View mail");
	header_menu("View mail");
	$mbox = @imap_open("{".$prefs["pop_server"]."/pop3:".$prefs["pop_port"]."}INBOX",$username,$password);
	$mbox_header = @imap_header($mbox, $msgid); 
	
	// Message retrieved successfuly? 
	if (!is_object($mbox_header)) {
		dialog ("Message retrieval error", $lang["mailview_msg_retrieveerror"], false);
		exit();
	}
	
	// Check if message need to be tagged 'seen' or if it already is.
	$mbox_read=0;
	if ($filemsg = @file($prefs["prefdir"].$username.".msg")) {
		for ($i=0; $i!=count($filemsg); $i++) {
			if ($filemsg[$i]==$mbox_header->message_id."\n") {
				$mbox_read = 1;
			}
		}
	}

	// Tag a message as seen by adding it to the seen msgs file.
	if ($mbox_read==0) {
		if (@$filemsg = fopen($prefs["prefdir"].$username.".msg","a")) {
			fputs ($filemsg, $mbox_header->message_id."\n");
			fclose ($filemsg);
		}
	}
	
	// Nicely format retrieved data
	@$date = date("l d F Y, h:i:s",$mbox_header->udate);
	@$from_name = $mbox_header->from[0]->personal;
	@$from_mail = $mbox_header->from[0]->mailbox."@".$mbox_header->from[0]->host;
	@$from_to   = $mbox_header->to;
	@$from_cc   = $mbox_header->cc;

	@$subject = htmlentities($mbox_header->subject);
	@$attachments = imap_fetchstructure($mbox, $msgid);

	// There needs to be a subject, so the user can click it.
	if ($subject=="") { $subject = $lang["mailview_lbl_nosubject"]; }

	// Show headers for this email
		?>
		<table width="100%" cellspacing="0" cellpadding="2" border="0">
			<tr>
			<td class="iface_header_bg">
				<b class="iface_header_fg">
					<?=$lang["mailview_lbl_date"]?>
				</b>
			</td>
			<td class="<?=rowstyle()?>">
				<font class="iface_main_fg">
					<?=$date?>
				</font>
			</td>
			</tr>

			<tr>
			<td class="iface_header_bg">
				<b class="iface_header_fg">
					<?=$lang["mailview_lbl_from"]?>
				</b>
			</td>
			<td class="<?=rowstyle()?>">
				<a class="iface_main_fg" href="pp.php?action=addressbook&frm_address[name]=<?=urlencode($from_name)?>&frm_address[email]=<?=$from_mail?>"><?=$from_name?> &lt;<?=$from_mail?>&gt;</a>
			</td>
			</tr>
			<?
			if (is_array($from_to)) {
				?>
				<tr>
					<td class="iface_header_bg" valign="top">
						<b><?=$lang["mailview_lbl_to"]?></b>
					</td>
					<td class="<?=rowstyle()?>">
						<font class="iface_main_fg">
						<?
							$final_to = "";
							foreach ($from_to as $to) {
								$final_to = "<a class=\"iface_main_fg\" href=\"pp.php?action=mailcompose&to=".$to->mailbox."@".$to->host."\">".$to->personal." &lt;".$to->mailbox."@".$to->host."&gt;</a>,";
							}
							
							$final_to[strlen($final_to)-1] = " ";
							echo $final_to;
						?>
						</font>
					</td>
				</tr>
				<?
			}
			if (is_array($from_cc)) {
				?>
				<tr>
					<td class="iface_header_bg" valign="top">
						<b><?=$lang["mailview_lbl_cc"]?></b>
					</td>
					<td class="<?=rowstyle()?>">
						<font class="iface_main_fg">
						<?
							$final_cc = "";
							foreach ($from_cc as $cc) {
								$final_cc .= "<a class=\"iface_main_fg\" href=\"pp.php?action=mailcompose&to=".$cc->mailbox."@".$cc->host."\">".$cc->personal." &lt;".$cc->mailbox."@".$cc->host."&gt;</a>,";
							}
							$final_cc[strlen($final_cc)-1] = " ";
							echo $final_cc;
						?>
						</font>
					</td>
				</tr>
				<?
			}
			?>
			<tr>
			<td class="iface_header_bg">
				<b class="iface_header_fg">
					<?=$lang["mailview_lbl_subject"]?>
				</b>
			</td>
			<td class="<?=rowstyle()?>">
				<font class="iface_main_fg">
					<?=$subject?>
				</font>
			</td>
			</tr>
		<?
		
		if ($headers=="full") {
			?>
				<tr>
				<td class="iface_header_bg" valign="top">
					<b class="iface_header_fg">
						<?=$lang["mailview_lbl_fullheaders"]?>
					</b>
				</td>
				<td class="<?=rowstyle()?>">
					<font class="iface_main_fg">
						<?=nl2br(htmlentities(imap_fetchbody($mbox, $msgid, 0)))?>
					</font>
				</td>
				</tr>
			<?
		}

		// Handle attachments
		if (@count($attachments->parts)>1) {
			?>
				<tr>
				<td class="iface_header_bg" valign="top"><b class="iface_header_fg"><?=$lang["mailview_lbl_attachments"]?></b></td>
				<td class="<?=rowstyle()?>">
					<table border="0" cellspacing="0" cellpadding="0" width="100%">
					
					<?
					for ($i=1; $i!=@count($attachments->parts); $i++) {
						// Find the name of the attachment, if any.
						$name = "Unknown";
						for ($j=0; $j!=count(@$attachments->parts[$i]->dparameters); $j++) {
							if (@strtolower(@$attachments->parts[$i]->dparameters[$j]->attribute)=="filename") {
								$name = @$attachments->parts[$i]->dparameters[$j]->value;
							}
						}

						// Get mime-encoding
						$type = @$attachments->parts[$i]->type;
						$subtype = @$attachments->parts[$i]->subtype;
			
						if ($type=="") { 
							$type = "text"; 
						} else {
							$type = $mime_enc[$type];
						}
						$subtype = strtolower($subtype);

						// Get encoding
						$encoding = @$attachments->parts[$i]->encoding;
						if ($encoding=="") { 
							$encoding = "7BIT"; 
						} else { 
							$encoding = strtoupper($trans_enc[$encoding]); 
						}
							
						// Get size
						$bytes = number_format(@$attachments->parts[$i]->bytes);
						// Show attachement information
						?>
						<tr>
						<td>
							<a class="iface_main_fg" href="pp.php?action=attachmentview&msgid=<?=$msgid?>&attachmentid=<?=$i?>"><?=$name?></a>
						</td>
						<td> 
							<font class="iface_main_fg">(<?=$type?>/<?=$subtype?>)</font> 
						</td>
						<td> 
							<font class="iface_main_fg"><?=$encoding?></font> 
						</td>
						<td align="right"> 
							<font class="iface_main_fg"><?=$bytes?> Bytes</font> 
						</td>
						</tr>
					<?
					}
					?>
					</table>
				</td>
				</tr>
			<?
		}
		
		?>
			<tr>
			<td colspan="2">
				<img height="1" width="1" alt="">
			</td>
			</tr>

			<tr cellpadding="10">
			<td class="iface_main_bg" colspan="2">
				<font class="iface_main_fg">
					<?
						// Message body
						if (count($attachments->parts) > 0) {
							echo mailpart_parse ($attachments->parts[0], $mbox, $msgid, "1");
						} else {
							echo mailpart_parse ($attachments, $mbox, $msgid, "1");
						}
					?>
				</font>
			</td>
			</tr>
		</table>
		<br>
		<a class="iface_main_fg" href="pp.php?action=mailcompose&msgid=<?=$msgid?>">
			<?=$lang["mailview_lnk_reply"]?>
		</a>
		&nbsp;|&nbsp;
               <a class="iface_main_fg" href="pp.php?action=maildelete&msgs=1&del[0]=<?=$mbox_header->message_id?>">
			<?=$lang["mailview_lnk_delete"]?>
		</a>  
		&nbsp;|&nbsp;
		<?
			if ($headers=="full") {
				?>
				<a class="iface_main_fg" href="pp.php?action=mailview&msgid=<?=$msgid?>">
					<?=$lang["mailview_lnk_shortheaders"]?>
				</a>  
				&nbsp;|&nbsp;
				<?
			} else {
				?>
				<a class="iface_main_fg" href="pp.php?action=mailview&msgid=<?=$msgid?>&headers=full">
					<?=$lang["mailview_lnk_fullheaders"]?>
				</a>  
				&nbsp;|&nbsp;
				<?
			}
		?>
		<a class="iface_main_fg" href="pp.php?"><?=$lang["mailview_lnk_returnmbox"]?></a>
		<br>
	<?

	imap_close ($mbox);
	
	footer_html();
}

//----------------------------------------------------------------------------
// Name   : act_mail_delete
// Desc   : Delete one or more email messages
// Params : del = array containing message id's to delete. The value for each 
//                filled element of the array will be taken as the msgid to 
//                delete. ($del[0] = 5; $del[10] = 6; Deletes msgid's 5 and 6)
// Returns: -
// Remarks: Calls act_inbox_show
//----------------------------------------------------------------------------
function act_mail_delete ($del) {
	global $username, $password, $prefs;

	if ($del == "") {
		return;
	}
	
	// Load 'seen' message list
	$filemsg = @file($prefs["prefdir"].$username.".msg");
	
	$mbox = imap_open("{".$prefs["pop_server"]."/pop3:".$prefs["pop_port"]."}INBOX",$username,$password);
	$mbox_num_msg = imap_num_msg($mbox);

	// Walk through mailbox to delete any mail
	for ($i=1; $i<=$mbox_num_msg; $i++) {
		$mbox_header = imap_header ($mbox, $i);
	
		// Walk through list of emails to be deleted
		// in order to find if this email should be deleted
		foreach ($del as $uid) {
			$message_id = $mbox_header->message_id;	

			// Check if message has been seen
			if ($message_id == $uid) {
				// Remove the delete message from the seen list
				$seen = 0;
				for ($s=0; $s!=count($filemsg) && $seen==0; $s++) {
					if ($filemsg[$s]==$uid."\n") {
						array_splice ($filemsg,$s,1);
						$seen = 1;
					}
				}
				imap_delete($mbox,$i);
			}
		}
	}

	imap_expunge ($mbox);
	imap_close($mbox);
	
	// Save the seen list
	if ($f = @fopen($prefs["prefdir"].$username.".msg","w")) {
		for ($s=0; $s!=count($filemsg); $s++) {
			fputs ($f,$filemsg[$s]);
		}
		fclose ($f);
	}
}

//----------------------------------------------------------------------------
// Name   : act_mail_compose
// Desc   : Let's the user compose or reply to a message.
// Params : to    = The email address (and possibly name) where to send the 
//                  message. This will pre-fill the 'to' field. May be ommited
//          msgid = Message id to which to reply. may be ommited
// Returns: -
// Remarks: If the msgid is given, that message's contents will be loaded, 
//          the 'to' field will be set to the message's sender, subject will 
//          be prepended with "Re: " and set, and the body will be quoted.
//----------------------------------------------------------------------------
function act_mail_compose ($to="", $msgid="") {
	global $username, $password, $lang, $prefs;
	
	$address = load_address($prefs["prefdir"].$username.".address");
	
	// Are we replying? 
	if ($msgid != "") {
		$mbox = imap_open("{".$prefs["pop_server"]."/pop3:".$prefs["pop_port"]."}INBOX",$username,$password);
		$mbox_header = imap_header($mbox, $msgid); 
		$structure = imap_fetchstructure($mbox, $msgid);

		if (count($structure->parts) > 0) {
			$body = mailpart_parse($structure->parts[0], $mbox, $msgid, "1");
		} else {
			$body = mailpart_parse($structure, $mbox, $msgid, "1");
		}
		$body = strip_tags($body);
		
		// Nicely format retrieved data for reply
		@$date    = $mbox_header->date;
		@$to      = $mbox_header->from[0]->mailbox."@".$mbox_header->from[0]->host;
		@$subject = "Re: ".$mbox_header->subject;
		// NOTE : i18n
		@$body    = "On $date, $to wrote:\n\n> ".str_replace("\n","\n> ",$body)."\n\n";
		
		imap_close($mbox);
	}
	
	// Append signature
	@$body .= $prefs["signature"];
	
	header_html($lang["title_compose"]);
	header_menu($lang["title_compose"]);
	?>
	<center>
	<table width="100%" cellspacing="0" cellpadding="2" border="0">
	<form enctype="multipart/form-data" action="pp.php" method="post">
		<input type="hidden" name="action" value="mailsend">

		<tr>
		<td class="iface_header_bg">&nbsp; 
			<b class="iface_header_fg"><?=$lang["mailcompose_lbl_to"]?></b>
		</td>
		<td class="iface_main_bg_hi">&nbsp; 
			<input type="text" name="frm_compose[to]" value="<?=@htmlentities(stripslashes($to))?>" size="40">
			<? 
				if (count($address)>0) { 
				?>
					<select name="frm_compose[addressbook_to]" size="1" onchange="to=this.form.elements['frm_compose[to]'];if(to.value.length>0){to.value+=',';};to.value+=this.form.elements['frm_compose[addressbook_to]'].options[this.form.elements['frm_compose[addressbook_to]'].selectedIndex].value">
					<option value="" selected>&nbsp;
						<?
							for ($i=0; $i!=count($address); $i++) {
								echo "<option value=\"".htmlentities(stripslashes($address[$i][1]))."\">".htmlentities(stripslashes($address[$i][0]))."\n";
							}
						?>
					</select>
					<input type="button" value="clear" onclick="this.form.elements['frm_compose[to]'].value=''; this.form.elements['frm_compose[addressbook_to]'].value=''">
					<input type="button" value=" ? " onclick="alert('<?=$lang["help_compose_to"]?>');">
				<?
				}
			?>
		</td>
		</tr>
		
		<tr>
		<td class="iface_header_bg">&nbsp; 
			<b class="iface_header_fg"><?=$lang["mailcompose_lbl_cc"]?>:</b>
		</td>
		<td class="iface_main_bg">&nbsp; 
			<input type="text" name="frm_compose[cc]" size="40"> 
			<? 
				if (count($address)>0) { 
				?>
					<select name="frm_compose[addressbook_cc]" size="1" onchange="cc=this.form.elements['frm_compose[cc]'];if(cc.value.length>0){cc.value+=',';};cc.value+=this.form.elements['frm_compose[addressbook_cc]'].options[this.form.elements['frm_compose[addressbook_cc]'].selectedIndex].value">
						<option value="" selected>&nbsp;
						<?
							for ($i=0; $i!=count($address); $i++) {
								echo "<option value=\"".htmlentities(stripslashes($address[$i][1]))."\">".htmlentities(stripslashes($address[$i][0]))."\n";
							}
						?>
					</select>
					<input type="button" value="clear" onclick="this.form.elements['frm_compose[cc]'].value=''; this.form.elements['frm_compose[addressbook_cc]'].value=''">
					<input type="button" value=" ? " onclick="alert('<?=$lang["help_compose_cc"]?>');">
				<?
				}
			?>
		</td>
		</tr>
		
		<tr>
		<td class="iface_header_bg">&nbsp; 
			<b class="iface_header_fg"><?=$lang["mailcompose_lbl_bcc"]?>:</b>
		</td>
		<td class="iface_main_bg">&nbsp; 
			<input type="text" name="frm_compose[bcc]" size="40">
			<? 
				if (count($address)>0) { 
				?>
					<select name="frm_compose[addressbook_bcc]" size="1" onchange="bcc=this.form.elements['frm_compose[bcc]'];if(bcc.value.length>0){bcc.value+=',';};bcc.value+=this.form.elements['frm_compose[addressbook_bcc]'].options[this.form.elements['frm_compose[addressbook_bcc]'].selectedIndex].value">
						<option value="" selected>&nbsp;
						<?
							for ($i=0; $i!=count($address); $i++) {
								echo "<option value=\"".htmlentities(stripslashes($address[$i][1]))."\">".htmlentities(stripslashes($address[$i][0]))."\n";
							}
						?>
					</select>
					<input type="button" value="clear" onclick="this.form.elements['frm_compose[bcc]'].value=''; this.form.elements['frm_compose[addressbook_bcc]'].value=''">
					<input type="button" value=" ? " onclick="alert('<?=$lang["help_compose_bcc"]?>');">
				<?
				}
			?>
		</td>
		</tr>
		
		<tr>
		<td class="iface_header_bg">&nbsp; 
			<b class="iface_header_fg"><?=$lang["mailcompose_lbl_attachment"]?></b>
		</td>           
		<td class="iface_main_bg">&nbsp; 
			<input type="file" name="attachment" size="31">
			<input type="button" value=" ? " onclick="alert('<?=$lang["help_compose_attach"]?>');">
		</td>
		</tr>
		
		<tr>
		<td class="iface_header_bg">&nbsp; 
			<b class="iface_header_fg"><?=$lang["mailcompose_lbl_subject"]?>:</b>
		</td>
		<td class="iface_main_bg_hi">&nbsp; 
			<input type="text" name="frm_compose[subject]" value="<?=@$subject?>" size="40">
		</td>
		</tr>
		
		<tr>
		<td class="iface_header_bg" valign="top"><br>&nbsp; 
			<b class="iface_header_fg"><?=$lang["mailcompose_lbl_body"]?></b>
		</td>
		<td class="iface_main_bg">&nbsp; 
			<textarea name="frm_compose[body]" cols="80" rows="20"><? echo @$body; ?></textarea>
			<br><br>
		</td>
		</tr>
		
		<tr>
		<td class="iface_header_bg">&nbsp;  
		</td>
		<td class="iface_main_bg_hi">&nbsp; 
			<input type="submit" value="<?=$lang["mailcompose_btn_send"]?>">
		</td>
		</tr>
		
		</form>
		</table>
		</center>
	<?
	footer_html();
	
}

//----------------------------------------------------------------------------
// Name   : act_mail_send
// Desc   : Sends an email composed with act_mail_compose.
// Params : frm_compose : Associative array filled by act_mail_compose.
// Returns: -
// Remarks: If any files have been uploaded via HTTP upload, they will be 
//          attached to the message.
//----------------------------------------------------------------------------
function act_mail_send ($frm_compose) { 
	global $username, $password, $lang, $prefs;

	if ($prefs["fullname"]=="") { $prefs["fullname"]=$username; }
	if ($prefs["email"]=="") { $prefs["email"] = stripslashes($username)."@".$prefs["hostname"]; }
	
	// If there's no to field specified, use the addressbook entry
	// This will only occur if the client has no javascript
	if ($frm_compose["to"]=="") {
		$frm_compose["to"] = $frm_compose["addressbook_to"];
	}
	if ($frm_compose["cc"]=="") {
		$frm_compose["cc"] = $frm_compose["addressbook_cc"];
	}
	if ($frm_compose["bcc"]=="") {
		$frm_compose["bcc"] = $frm_compose["addressbook_bcc"];
	}
	
	$frm_compose["to"] = stripslashes($frm_compose["to"]);
	$frm_compose["cc"] = stripslashes($frm_compose["cc"]);
	$frm_compose["bcc"] = stripslashes($frm_compose["bcc"]);
	
	// Compose mail headers
	// FIXME als geen volledige naam, dan niet "volledige naam"
	$headers = "From: \"".$prefs["fullname"]."\" <".$prefs["email"].">\n";

	if ($frm_compose["cc"]!="")  { $headers .= "Cc: ".$frm_compose["cc"]."\n"; }
	if ($frm_compose["bcc"]!="") { $headers .= "Bcc: ".$frm_compose["bcc"]."\n"; }

	// Add Errors-To: header to prevent bounced mail going to webuser (thanx to vdong)
	$headers .= "Errors-To: ".$username."@".$prefs["hostname"]."\n";
				 
	// Handle attachment if there is one.
	// This part was created with the help of Alexander Rafael Benatti's sendmail.php script.
	if ($_FILES["attachment"]["tmp_name"]!="none" && $_FILES["attachment"]["tmp_name"]!="") {
		// Add headers for recognition of attachments
		$headers .= "MIME-version: 1.0\n";
		$headers .= "Content-type: multipart/mixed; boundary=\"Message-Boundary\"\n";
		$headers .= "Content-transfer-encoding: 7BIT\n\n";

		// Add attachment seperation headers for main body
		$body_top = "--Message-Boundary\n";
		$body_top .= "Content-Type: text/plain; charset=us-ascii\n\n";
	
		$frm_compose["body"] = $body_top .= $frm_compose["body"] . "\n";
		
		// Add attachment seperation header for attachment
		$frm_compose["body"] .= "--Message-Boundary\n";
		$frm_compose["body"] .= "Content-type: ".$_FILES["attachment"]["type"]."; name=\"".$_FILES["attachment"]["name"]."\"\n";
		$frm_compose["body"] .= "Content-Transfer-Encoding: BASE64\n";
		$frm_compose["body"] .= "Content-disposition: attachment; filename=\"".$_FILES["attachment"]["name"]."\"\n\n";

		// Attach base64 mime-encoded attachment
		$f = fopen($_FILES["attachment"]["tmp_name"], "r");
		$contents = fread($f, $_FILES["attachment"]["size"]);
		$encoded_attach = chunk_split(base64_encode($contents));
		fclose($f);
		
		$frm_compose["body"] .= $encoded_attach;
	}
	
	header_html($lang["title_mailsend"]);
	header_menu($lang["title_mailsend"]);
	
	$headers = chop($headers);

	if (mail (
		$frm_compose["to"], 
		stripslashes($frm_compose["subject"]), 
		stripslashes($frm_compose["body"]),
		$headers)
	) {
		$msg = "<p><b>";
		$msg .= $lang["mailsend_msg_confirmation"];
		$msg .= "</b><blockquote>";
		$msg .= $frm_compose["to"];
		if ($frm_compose["cc"]!="") {
			$msg .= $frm_compose["cc"];
		}
		if ($frm_compose["bcc"]!="") {
			$msg .= ", ".$frm_compose["bcc"];
		}
		
		$msg .= "</blockquote></p><br>";
		$msg .= "<a href=\"pp.php\">".$lang["mailsend_lnk_returnmbox"]."</font></a><br>";

		dialog ($lang["mailsend_msg_confirmation"], $msg, false);
	} else {
		$msg = "<b>".$lang["mailsend_msg_mailfailed"]."<br><br>";
		$msg .= "<a href=\"pp.php\">".$lang["mailsend_lnk_returnmbox"]."</a>";
		dialog ($lang["mailsend_msg_failed"], $msg, false);
	}
	footer_html();
	
}

//----------------------------------------------------------------------------
// Name   : act_attachment_view
// Desc   : Send an attachment from a email to the browser.
// Params : msgid        = Email message id to which the attachment belongs.
//          attachmentid = The attachment id to send to the browser.
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function act_attachment_view ($msgid, $attachmentid) {
	global $username, $password, $prefs, $mime_enc, $trans_enc;
	
	//*************************************************************
	// NOTICE: This block of code which retrieves information on 
	//         the attachment is a duplicate, and should be unified
	//         into a single function.
	//*************************************************************
	
	$mbox = imap_open("{".$prefs["pop_server"]."/pop3:".$prefs["pop_port"]."}INBOX",$username,$password);
	$mbox_header = imap_header($mbox, $msgid); 
	$attachments = imap_fetchstructure($mbox, $msgid);
	
	// Find the name of the attachment, if any.
	$name = "Unknown";
	for ($j=0; $j!=count(@$attachments->parts[$attachmentid]->dparameters); $j++) {
		if (@strtolower(@$attachments->parts[$attachmentid]->dparameters[$j]->attribute)=="filename") {
			$name = @$attachments->parts[$attachmentid]->dparameters[$j]->value;
		}
	}
	
	// Get mime-encoding
	$type = @$attachments->parts[$attachmentid]->type;
	$subtype = @$attachments->parts[$attachmentid]->subtype;
	if ($type=="") { 
		$type = "text"; 
	} else {
		$type = $mime_enc[$type];
	}
	$subtype = strtolower($subtype);
	
	// Get encoding
	$encoding = @$attachments->parts[$attachmentid]->encoding;
	if ($encoding=="") { 
		$encoding = "7BIT"; 
	} else { 
		$encoding = strtoupper($trans_enc[$encoding]); 
	}
	
	// Get size
	$bytes = @$attachments->parts[$attachmentid]->bytes;
	
	// Now give the client some headers 
	// This piece of code was based on that of Neomail

	// NOTICE: This still seems buggy in some browsers (IE)
	//         Needs extensive testing
	header ("Content-Length: $bytes");
	header ("Content-Transfer-Coding: binary");
	header ("Connection: close");
	header ("Content-Type: $type/$subtype; name=\"$name\"");
	header ("Content-Disposition: attachment; filename=\"$name\"");
	
	// Determine if we should decode the body, and then pass it to the client.
	if ($encoding=="BASE64") {
		echo imap_base64(imap_fetchbody($mbox, $msgid, $attachmentid+1));
	} else 
	if ($encoding == "QUOTED_PRINTABLE") {
		echo imap_qprint(imap_fetchbody($mbox, $msgid, $attachchmentid+1));
	} else {
		echo imap_fetchbody($mbox, $msgid, $attachmentid+1);
	}

	imap_close($mbox);
	
}

//----------------------------------------------------------------------------
// Name   : act_preferences
// Desc   : Wrapper function for preferences editor
// Params : subaction = Sub action to determine what this wrapper func should 
//                      do
//          frm_pref  = Associative array with preferences form information
//                      gotten from act_preferences_edit
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function act_preferences($subaction, $frm_pref) {
	global $lang;

	//------------------------------------------------------------------------
	// Name   : act_preferences_edit
	// Desc   : Show html code with form which allows user to edit his prefs
	// Params : - 
	// Returns: -
	// Remarks: -
	//------------------------------------------------------------------------
	function act_preferences_edit() {
		global $lang, $prefs;
		
		$languages = load_avail_languages($prefs["prefdir"]."languages/");
		$themes = load_avail_themes($prefs["prefdir"]."themes/");
		
		header_html($lang["title_prefs"]);
		header_menu($lang["title_prefs"]);
		?>
		<table width="100%" cellspacing="0" cellpadding="2" border="0">
		<form action="pp.php" method="post">
			<input type="hidden" name="action"    value="preferences">
			<tr><td class="iface_header_bg">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_fullname"]?>:</b></td><td class="iface_main_bg">&nbsp; <input type="text" name="frm_pref[fullname]" size="35" value="<?=$prefs["fullname"]?>"></td></tr>
			<tr><td class="iface_header_bg">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_email"]?>:</b></td><td class="iface_main_bg">&nbsp; <input type="text" name="frm_pref[email]" size="35" value="<?=$prefs["email"]?>"></td></tr>
			<tr><td class="iface_header_bg">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_sortmbox"]?>:</b></td><td class="iface_main_bg">&nbsp; 
				<select name="frm_pref[sort]">
					<option value="date" <? if ($prefs["sort"]=='date') { echo "selected"; } ?>><?=$lang["preferences_itm_sort_date"]?>
					<option value="from" <? if ($prefs["sort"]=='from') { echo "selected"; } ?>><?=$lang["preferences_itm_sort_from"]?>
					<option value="subj" <? if ($prefs["sort"]=='subj') { echo "selected"; } ?>><?=$lang["preferences_itm_sort_subject"]?>
				</select>
			</td></tr>
			<tr><td class="iface_header_bg">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_filter"]?>:</b></td><td class="iface_main_bg">&nbsp; 
				<select name="frm_pref[filter]">
					<option value="all" <? if ($prefs["filter"]=='all') { echo "selected"; } ?>><?=$lang["preferences_itm_filter_all"]?>
					<option value="new" <? if ($prefs["filter"]=='new') { echo "selected"; } ?>><?=$lang["preferences_itm_filter_new"]?>
					<option value="old" <? if ($prefs["filter"]=='old') { echo "selected"; } ?>><?=$lang["preferences_itm_filter_old"]?>
				</select>
			</td></tr>
			<tr><td class="iface_header_bg">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_language"]?>:</b></td><td class="iface_main_bg">&nbsp; 
				<select name="frm_pref[language]">
				<?
					foreach ($languages as $value) {
						?><option value="<?=$value?>" <? if ($prefs["language"]==$value) { echo "selected"; }?>><?=$value?><?
					}
				?>
				</select>
			</td></tr>
			<tr><td class="iface_header_bg">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_theme"]?>:</b></td><td class="iface_main_bg">&nbsp; 
				<select name="frm_pref[theme]">
				<?
					foreach ($themes as $value) {
						?><option value="<?=$value?>" <? if ($prefs["theme"]==$value) { echo "selected"; }?>><?=$value?><?
					}
				?>
				</select>
			</td></tr>
			<tr><td class="iface_header_bg" valign="top">&nbsp; <b class="iface_header_fg"><?=$lang["preferences_lbl_signature"]?>:</b></td><td class="iface_main_bg">&nbsp; <textarea name="frm_pref[signature]" cols="80" rows="4"><?=$prefs["signature"]?></textarea></font><br><br></td></tr>

			<tr><td class="iface_header_bg">&nbsp;</td><td class="iface_main_bg_hi">&nbsp;<input type="submit" name="subaction" value="<?=$lang["preferences_btn_save"]?>"></font></td></tr>
		</form>
		</table>
		<?

		footer_html();
		
	}
	
	//------------------------------------------------------------------------
	// Name   : act_preferences_save
	// Desc   : Saves the information gotten from act_preferences_edit
	// Params : frm_pref = associative array filled with data gotten from 
	//                     act_preferences_edit
	// Returns: -
	// Remarks: -
	//------------------------------------------------------------------------
	function act_preferences_save($frm_pref) {
		global $username, $password, $prefs, $lang, $theme;
		
		if ($f = @fopen($prefs["prefdir"].$username.".prefs","w")) {
			foreach ($frm_pref as $key=>$value) {
				$fcontents .= $key."=".str_replace("\n", "\\n", $value)."\n";
			}
			fputs ($f,$fcontents);
			fclose ($f);
			$prefs = array_merge ($prefs, load_prefs ($prefs["prefdir"]."/".$username.".prefs"));

			/* Reload just saved preferences */
			$lang  = load_lang($prefs["prefdir"]."languages/".$prefs["language"].".lang");
			$theme = load_theme ($prefs["prefdir"]."themes/".$prefs["theme"].".pt");
		} else {
			header_html($lang["title_saveprefs"]);
			header_menu($lang["title_saveprefs"]);

			$msg = "<a href=\"pp.php\">".$lang["menu_lnk_mboxview"]."</a><br>";
			dialog ($lang["preferences_msg_cantsave"], $msg, false);

			footer_html();
			exit();
		}
	}
	
	if ($subaction == $lang["preferences_btn_save"]) { 
		act_preferences_save($frm_pref); 
	}
	
	act_preferences_edit(); 
}

//----------------------------------------------------------------------------
// Name   : act_addressbook
// Desc   : Wrapper function for address book editor
// Params : subaction   = Sub action to determine what this wrapper func should
//                        do
//          frm_address = Associative array with preferences form information
//                        gotten from act_address_edit
// Returns: -
// Remarks: -
//----------------------------------------------------------------------------
function act_addressbook($subaction, $frm_address) {
	global $lang;
	
	//------------------------------------------------------------------------
	// Name   : act_addressbook_edit
	// Desc   : Shows html code and form through which the user can load and
	//          edit addressbook entries.
	// Params : frm_address = associative array filled with data gotten from 
	//                        act_address_edit (self, for loading purpose)
	// Returns: -
	// Remarks: This function gets data from itself in the form of an address
	//          id which it should load so the user can edit it.
	//------------------------------------------------------------------------
	function act_addressbook_edit($frm_address) {
		global $username, $password, $prefs, $lang;
		
		$address = load_address($prefs["prefdir"].$username.".address");
		
		// Editing an existing address?
		if ($frm_address["entry"]!="") {
			$frm_address["name"] = $address[$frm_address["entry"]][0];
			$frm_address["email"] = $address[$frm_address["entry"]][1];
		}		

		header_html($lang["title_editadr"]);
		header_menu($lang["title_editadr"]);
		?>
			<table width="100%" cellspacing="0" cellpadding="2" border="0">
				<?
				// Show current addresses in the addressbook
				?>
				<form action="pp.php" method="post">
				<input type="hidden" name="action" value="addressbook">

				<tr class="iface_header_bg"><td colspan="2"><b class="iface_header_fg"><?=$lang["addressbook_lbl_loadentry"]?></b></td></tr>
				
				<tr class="iface_main_bg">
				<td colspan="2">
					
						<select name="frm_address[entry]">
							<option value=""><?=$lang["addressbook_itm_new"]?>
							<?
								// Walk through list of addresses
								for ($i=0; $i!=count($address); $i++) {
									?><option value="<?=$i?>"><?=$address[$i][0]?><?
								}
							?>
						</select>
					</font>
					&nbsp;
					<input name="subaction" type="submit" value="<?=$lang["addressbook_btn_load"]?>">
					<input type="button" value=" ? " onclick="alert('<?=$lang["help_addr_load"]?>')">
					<br><br>
				</td>
				</tr>

				</form>

				<form action="pp.php" method="post">
				<input type="hidden" name="action" value="addressbook">
				<? 
				if ($frm_address["name"]!="" || $frm_address["email"]!="") {
					?>
					<input type="hidden" name="frm_address[id]" value="<?=$frm_address["entry"]?>">
					<?
				} 
				?>

				<tr class="iface_header_bg">
				<td colspan="2">
					<b class="iface_header_fg">
						<?
							// New address or editing an existing one?
							if ($frm_address["entry"]!="") { 
								echo $lang["addressbook_lbl_entry_modify"]; 
							} else {
								echo $lang["addressbook_lbl_entry_new"];
							}
						?>
					</b>
				</td>
				</tr>

				<tr class="iface_main_bg">
				<td width="1%" ><b class="iface_main_fg"><?=$lang["addressbook_lbl_name"]?></b></td>
				<td><b class="iface_main_fg"><?=$lang["addressbook_lbl_email"]?></b></td>
				</tr>

				<tr class="iface_main_bg">
				<td width="1%" ><input type="text" name="frm_address[name]" size="30" value="<?=htmlentities(stripslashes($frm_address["name"]))?>"></font></td>
				<td><input type="text" name="frm_address[email]" size="30" value="<?=htmlentities(stripslashes($frm_address["email"]))?>"></font> &nbsp; <input name="subaction" type="submit" value="<?=$lang["addressbook_btn_save"]?>"><? if ($frm_address["entry"]!="") { echo "&nbsp; <input type=\"submit\" name=\"subaction\" value=\"".$lang["addressbook_btn_delete"]."\">"; } ?></font></td>
				</tr>
				</form>
			</table>
		<?
		footer_html();
	}
	
	//------------------------------------------------------------------------
	// Name   : act_addressbook_save
	// Desc   : Saves modified and new address book entries.
	// Params : frm_address = associative array filled with data gotten from 
	//                        act_address_edit
	// Returns: -
	// Remarks: -
	//------------------------------------------------------------------------
	function act_addressbook_save($frm_address) {
		global $username, $password, $prefs, $lang;
		
		$address = load_address($prefs["prefdir"].$username.".address");
		
		if ($frm_address["id"]!="") {
		// Change existing address
		
			// Update correct item in address list
			$address[$frm_address["id"]][0] = $frm_address["name"];
			$address[$frm_address["id"]][1] = $frm_address["email"];
			
			// Save the new list to file
			if ($f = @fopen($prefs["prefdir"].$username.".address","w")) {
				for ($i=0; $i!=count($address); $i++) {
					fputs ($f,$address[$i][0].";".$address[$i][1]."\n");
				}
				fclose ($f);
			} else {
				// Error
				header_html($lang["title_saveadr"]);
				header_menu($lang["title_saveadr"]);

				echo $lang["addressbook_msg_cantsave"];
			
				?><a href="pp.php"><?=$lang["menu_lnk_mboxview"]?></a><br><?

				footer_html();
				
				exit;
			}
		} else {
		// Add new address
		
			// Add a entry to the file
			if ($f = @fopen($prefs["prefdir"].$username.".address","a")) {
				fputs ($f,$frm_address["name"].";".$frm_address["email"]."\n");
				fclose ($f);
			} else {
				// Error
				header_html($lang["title_saveadr"]);
				header_menu($lang["title_saveadr"]);
				
				echo $lang["addressbook_msg_cantsave"];
			
				?><a href="pp.php"><?=$lang["menu_lnk_mboxview"]?></a><br><?

				footer_html();
				
				exit;
			}
		}
	}
	
	//------------------------------------------------------------------------
	// Name   : act_addressbook_delete
	// Desc   : Delete address book entries.
	// Params : frm_address = associative array filled with data gotten from 
	//                        act_address_edit containing the id to delete.
	// Returns: -
	// Remarks: -
	//------------------------------------------------------------------------
	function act_addressbook_delete($frm_address) {
		global $username, $password, $prefs, $lang;
		
		$address = load_address($prefs["prefdir"].$username.".address");
		$c=0;
		
		// Build a new address list and leave out selected entry
		for ($i=0; $i!=count($address); $i++) {
			if ($i!=$frm_address["id"]) {
				$new_address[$c]=$address[$i][0].";".$address[$i][1]."\n";
				$c++;
			}
		}
		
		// Save the new list to file
		$f = fopen($prefs["prefdir"].$username.".address","w");
		for ($i=0; $i!=count($new_address); $i++) {
			fputs ($f,$new_address[$i]);
		}
		fclose ($f);
	}
	
	// Deterime addressbook function action
	if ($subaction == $lang["addressbook_btn_save"]) { 
		act_addressbook_save ($frm_address); 
		unset($frm_address);
	} else 
	if ($subaction == $lang["addressbook_btn_delete"]) { 
		act_addressbook_delete ($frm_address); 
		unset($frm_address);
	}
	act_addressbook_edit ($frm_address);
	
}

// 
// That's it! Found anything weird, peculiar, ugly or just want to say 'hi', 
// you can (and may) reach me at <%%EMAIL>
//

?>
