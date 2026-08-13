<?php
// $Id: mainmenu.php,v 1.20 2010/08/23 04:40:59 sandking Exp $

/*
    This file is part of WebChess. http://webchess.sourceforge.net
	Copyright 2010 Jonathan Evraire, Rodrigo Flores

    WebChess is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WebChess is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with WebChess.  If not, see <http://www.gnu.org/licenses/>.
*/

	session_start();

	/* load settings */
	if (!isset($_CONFIG))
		require 'config.php';

	/* load external functions for setting up new game */
	require 'chessutils.php';
	require 'chessconstants.php';
	require 'newgame.php';
	require 'chessdb.php';
        require 'lang.php';


	/* allow WebChess to be run on PHP systems < 4.1.0, using old http vars */
	fixOldPHPVersions();

	/* if this page is accessed directly (ie: without going through login), */
	/* player is logged off by default */
	if (!isset($_SESSION['playerID']))
		$_SESSION['playerID'] = -1;

	/* connect to database */
	require 'connectdb.php';

	/* cleanup dead games */
	/* determine threshold for oldest game permitted */
	$targetDate = date("Y-m-d", mktime(0,0,0, date('m'), date('d') - $CFG_EXPIREGAME, date('Y')));

	/* find out which games are older */
	$tmpQuery = "SELECT gameID FROM " . $CFG_TABLE['games'] . " WHERE lastMove < '".$targetDate."'";
	$tmpOldGames = mysqli_query($dbh, $tmpQuery);

	/* for each older game... */
	while($tmpOldGame = mysqli_fetch_assoc($tmpOldGames))
	{
		/* ... clear the history... */
		mysqli_query($dbh, "DELETE FROM " . $CFG_TABLE['history'] . " WHERE gameID = ".$tmpOldGame['gameID']);

		/* ... and the board... */
		mysqli_query($dbh, "DELETE FROM " . $CFG_TABLE['pieces'] . " WHERE gameID = ".$tmpOldGame['gameID']);

		/* ... and the messages... */
		mysqli_query($dbh, "DELETE FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ".$tmpOldGame['gameID']);

		/* ... and finally the game itself from the database */
		mysqli_query($dbh, "DELETE FROM " . $CFG_TABLE['games'] . " WHERE gameID = ".$tmpOldGame['gameID']);
	}

	$tmpNewUser = false;
	$errMsg = "";
	$toDo = isset($_POST['ToDo']) ? $_POST['ToDo'] : '';
	switch($toDo)
	{
		case 'NewUser':
			/* create new player */
			$tmpNewUser = true;

			/* sanity check: empty nick */
			if ($_POST['txtNick'] == "")
				die("ERROR: must supply a valid nick!");

			/* check for existing user with same nick */
			$tmpQuery = "SELECT playerID FROM " . $CFG_TABLE['players'] . " WHERE nick = '".$_POST['txtNick']."'";
			$existingUsers = mysqli_query($dbh, $tmpQuery);
			if (mysqli_num_rows($existingUsers) > 0)
			{
				require 'newuser.php';
				die();
			}

			$tmpQuery = "INSERT INTO " . $CFG_TABLE['players'] . " (password, firstName, lastName, nick) VALUES ('".$_POST['pwdPassword']."', '".$_POST['txtFirstName']."', '".$_POST['txtLastName']."', '".$_POST['txtNick']."')";
			mysqli_query($dbh, $tmpQuery);

			/* get ID of new player */
			$_SESSION['playerID'] = mysqli_insert_id($dbh);

			/* set History format preference */
			$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'history', '".$_POST['rdoHistory']."')";
			mysqli_query($dbh, $tmpQuery);

			/* set History layout preference */
			$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'historylayout', '".$_POST['rdoHistorylayout']."')";
			mysqli_query($dbh, $tmpQuery);

			/* set Theme preference */
			$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'theme', '".$_POST['rdoTheme']."')";
			mysqli_query($dbh, $tmpQuery);

      /* set GUI language preference */
      $tmpLanguage = (isset($_POST['rdoLanguage']) && ($_POST['rdoLanguage'] == 'de')) ? 'de' : 'en';
      $tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'language', '".$tmpLanguage."')";
      mysqli_query($dbh, $tmpQuery);

			/* set auto-reload preference */
			if (is_numeric($_POST['txtReload']))
			{
				if (intval($_POST['txtReload']) >= $CFG_MINAUTORELOAD)
					$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'autoreload', ".$_POST['txtReload'].")";
				else
					$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'autoreload', ".$CFG_MINAUTORELOAD.")";

				mysqli_query($dbh, $tmpQuery);
			}

			/* set email notification preference */
			if ($CFG_USEEMAILNOTIFICATION)
			{
				$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", 'emailnotification', '".$_POST['txtEmailNotification']."')";
				mysqli_query($dbh, $tmpQuery);
			}

			/* no break, login user */

		case 'Login':
			/* check for a player with supplied nick and password */
			$tmpQuery = "SELECT * FROM " . $CFG_TABLE['players'] . " WHERE nick = '".$_POST['txtNick']."' AND password = '".$_POST['pwdPassword']."'";
			$tmpPlayers = mysqli_query($dbh, $tmpQuery);
			$tmpPlayer = mysqli_fetch_assoc($tmpPlayers);

			/* if such a player exists, log him in... otherwise die */
			if ($tmpPlayer)
			{
				$_SESSION['playerID'] = $tmpPlayer['playerID'];
				$_SESSION['lastInputTime'] = time();
				$_SESSION['playerName'] = $tmpPlayer['firstName']." ".$tmpPlayer['lastName'];
				$_SESSION['firstName'] = $tmpPlayer['firstName'];
				$_SESSION['lastName'] = $tmpPlayer['lastName'];
				$_SESSION['nick'] = $tmpPlayer['nick'];
			}
			else {
				echo "<script>alert('Invalid Nick or Password. Please try again'); window.location.replace('index.php');</script>\n";
				exit();
			}

			/* load user preferences */
			$tmpQuery = "SELECT * FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ".$_SESSION['playerID'];
			$tmpPreferences = mysqli_query($dbh, $tmpQuery);

			$isPreferenceFound['history'] = false;
			$isPreferenceFound['historylayout'] = false;
			$isPreferenceFound['theme'] = false;
      $isPreferenceFound['language'] = false;
			$isPreferenceFound['autoreload'] = false;
			$isPreferenceFound['emailnotification'] = false;

			while($tmpPreference = mysqli_fetch_assoc($tmpPreferences))
			{
				switch($tmpPreference['preference'])
				{
					case 'history':
					case 'historylayout':
					case 'theme':
            case 'language':
						/* setup SESSION var of name pref_PREF, like pref_history */
						$_SESSION['pref_'.$tmpPreference['preference']] = $tmpPreference['value'];
						break;

					case 'emailnotification':
						if ($CFG_USEEMAILNOTIFICATION)
							$_SESSION['pref_emailnotification'] = $tmpPreference['value'];
						break;

					case 'autoreload':
						if (is_numeric($tmpPreference['value']))
						{
							if (intval($tmpPreference['value']) >= $CFG_MINAUTORELOAD)
								$_SESSION['pref_autoreload'] = intval($tmpPreference['value']);
							else
								$_SESSION['pref_autoreload'] = $CFG_MINAUTORELOAD;
						}
						else
							$_SESSION['pref_autoreload'] = $CFG_MINAUTORELOAD;
						break;
				}

				$isPreferenceFound[$tmpPreference['preference']] = true;
			}

			/* look for missing preference and fix */
			foreach (array_keys($isPreferenceFound, false) as $missingPref)
			{
				$defaultValue = "";
				switch($missingPref)
				{
					case 'history':
						$defaultValue = "pgn";
						break;
					case 'historylayout':
						$defaultValue = "columns";
						break;
					case 'theme':
						$defaultValue = "beholder";
						break;
            case 'language':
              $defaultValue = "en";
              break;
					case 'autoreload':
						$defaultValue = $CFG_MINAUTORELOAD;
						break;
					case 'emailnotification':
						$defaultValue = "";
						break;
				}
				$tmpQuery = "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (".$_SESSION['playerID'].", '".$missingPref."', '".$defaultValue."')";
				mysqli_query($dbh, $tmpQuery);

				/* setup SESSION var of name pref_PREF, like pref_history */
				if ($CFG_USEEMAILNOTIFICATION || ($missingPref != 'emailnotification'))
					$_SESSION['pref_'.$missingPref] =  $defaultValue;
			}

			break;

		case 'Logout':
			$_SESSION = array();
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000,
					$params["path"], $params["domain"],
					$params["secure"], $params["httponly"]
				);
			}
			session_destroy();
			header('Location: index.php');
			exit();

		case 'InvitePlayer':
			/* prevent multiple pending requests between two players with the same originator */
			$tmpQuery = "SELECT gameID FROM " . $CFG_TABLE['games'] . " WHERE gameMessage = 'playerInvited'";
			$tmpQuery .= " AND ((messageFrom = 'white' AND whitePlayer = ".$_SESSION['playerID']." AND blackPlayer = ".$_POST['opponent'].")";
			$tmpQuery .= " OR (messageFrom = 'black' AND whitePlayer = ".$_POST['opponent']." AND blackPlayer = ".$_SESSION['playerID']."))";
			$tmpExistingRequests = mysqli_query($dbh, $tmpQuery);

			if (mysqli_num_rows($tmpExistingRequests) == 0)
			{
				if (!minimum_version("4.2.0"))
					init_srand();

				if ($_POST['color'] == 'random')
					$tmpColor = (mt_rand(0,1) == 1) ? "white" : "black";
				else
					$tmpColor = $_POST['color'];

				$tmpQuery = "INSERT INTO " . $CFG_TABLE['games'] . " (whitePlayer, blackPlayer, gameMessage, messageFrom, dateCreated, lastMove) VALUES (";
				if ($tmpColor == 'white')
					$tmpQuery .= $_SESSION['playerID'].", ".$_POST['opponent'];
				else
					$tmpQuery .= $_POST['opponent'].", ".$_SESSION['playerID'];

				$tmpQuery .= ", 'playerInvited', '".$tmpColor."', NOW(), NOW())";
				mysqli_query($dbh, $tmpQuery);

				/* if email notification is activated... */
				if ($CFG_USEEMAILNOTIFICATION)
				{
					/* if opponent is using email notification... */
					$tmpOpponentEmail = mysqli_query($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ".$_POST['opponent']." AND preference = 'emailNotification'");
					if (mysqli_num_rows($tmpOpponentEmail) > 0)
					{
						$opponentEmail = mysqli_fetch_row($tmpOpponentEmail)[0];
						if ($opponentEmail != '')
						{
							/* notify opponent of invitation via email */
							webchessMail('invitation', $opponentEmail, '', $_SESSION['nick'],'');
						}
					}
				}
			}
			break;

		case 'ResponseToInvite':
			if ($_POST['response'] == 'accepted')
			{
				/* update game data */
				$tmpQuery = "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = NULL, messageFrom = NULL WHERE gameID = ".$_POST['gameID'];
				mysqli_query($dbh, $tmpQuery);

				/* setup new board */
				$_SESSION['gameID'] = $_POST['gameID'];
				createNewGame($_POST['gameID']);
				saveGame();
			}
			else
			{

				$tmpQuery = "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = 'inviteDeclined', messageFrom = '".$_POST['messageFrom']."' WHERE gameID = ".$_POST['gameID'];
				mysqli_query($dbh, $tmpQuery);
			}

			break;

		case 'WithdrawRequest':

			/* get opponent's player ID */
			$tmpOpponentID = mysqli_query($dbh, "SELECT whitePlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ".$_POST['gameID']);
			if (mysqli_num_rows($tmpOpponentID) > 0)
			{
				$opponentID = mysqli_fetch_row($tmpOpponentID)[0];

				if ($opponentID == $_SESSION['playerID'])
				{
					$tmpOpponentID = mysqli_query($dbh, "SELECT blackPlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ".$_POST['gameID']);
					$opponentID = mysqli_fetch_row($tmpOpponentID)[0];
				}

				$tmpQuery = "DELETE FROM " . $CFG_TABLE['games'] . " WHERE gameID = ".$_POST['gameID'];
				mysqli_query($dbh, $tmpQuery);

				/* if email notification is activated... */
				if ($CFG_USEEMAILNOTIFICATION)
				{
					/* if opponent is using email notification... */
					$tmpOpponentEmail = mysqli_query($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ".$opponentID." AND preference = 'emailNotification'");
					if (mysqli_num_rows($tmpOpponentEmail) > 0)
					{
						$opponentEmail = mysqli_fetch_row($tmpOpponentEmail)[0];
						if ($opponentEmail != '')
						{
							/* notify opponent of invitation via email */
							webchessMail('withdrawal', $opponentEmail, '', $_SESSION['nick'], $_POST['gameID']);
						}
					}
				}
			}
			break;

		case 'UpdatePersonalInfo':
			$tmpQuery = "SELECT password FROM " . $CFG_TABLE['players'] . " WHERE playerID = ".$_SESSION['playerID'];
			$tmpPassword = mysqli_query($dbh, $tmpQuery);
			$dbPassword = mysqli_fetch_row($tmpPassword)[0];

			if ($dbPassword != $_POST['pwdOldPassword'])
				$errMsg = "Sorry, incorrect old password!";
			else
			{
				$tmpDoUpdate = true;

				if ($CFG_NICKCHANGEALLOWED)
				{
					$tmpQuery = "SELECT playerID FROM " . $CFG_TABLE['players'] . " WHERE nick = '".$_POST['txtNick']."' AND playerID <> ".$_SESSION['playerID'];
					$existingUsers = mysqli_query($dbh, $tmpQuery);

					if (mysqli_num_rows($existingUsers) > 0)
					{
						$errMsg = "Sorry, that nick is already in use.";
						$tmpDoUpdate = false;
					}
				}

				if ($tmpDoUpdate)
				{
					/* update DB */
					$tmpQuery = "UPDATE " . $CFG_TABLE['players'] . " SET firstName = '".$_POST['txtFirstName']."', lastName = '".$_POST['txtLastName']."', password = '".$_POST['pwdPassword']."'";

					if ($CFG_NICKCHANGEALLOWED && $_POST['txtNick'] != "")
						$tmpQuery .= ", nick = '".$_POST['txtNick']."'";

					$tmpQuery .= " WHERE playerID = ".$_SESSION['playerID'];
					mysqli_query($dbh, $tmpQuery);

					/* update current session */
					$_SESSION['playerName'] = $_POST['txtFirstName']." ".$_POST['txtLastName'];
					$_SESSION['firstName'] = $_POST['txtFirstName'];
					$_SESSION['lastName'] = $_POST['txtLastName'];

					if ($CFG_NICKCHANGEALLOWED && $_POST['txtNick'] != "")
						$_SESSION['nick'] = $_POST['txtNick'];
				}
			}

			break;

		case 'UpdatePrefs':
			/* Theme */
			$tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = '".$_POST['rdoTheme']."' WHERE playerID = ".$_SESSION['playerID']." AND preference = 'theme'";
			mysqli_query($dbh, $tmpQuery);

      /* GUI Language */
      $tmpLanguage = (isset($_POST['rdoLanguage']) && ($_POST['rdoLanguage'] == 'de')) ? 'de' : 'en';
      $tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = '".$tmpLanguage."' WHERE playerID = ".$_SESSION['playerID']." AND preference = 'language'";
      mysqli_query($dbh, $tmpQuery);

			/* History format */
			$tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = '".$_POST['rdoHistory']."' WHERE playerID = ".$_SESSION['playerID']." AND preference = 'history'";
			mysqli_query($dbh, $tmpQuery);

			/* History layout */
			$tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = '".$_POST['rdoHistorylayout']."' WHERE playerID = ".$_SESSION['playerID']." AND preference = 'historylayout'";
			mysqli_query($dbh, $tmpQuery);

			/* Auto-Reload */
			if (is_numeric($_POST['txtReload']))
			{
				if (intval($_POST['txtReload']) >= $CFG_MINAUTORELOAD)
					$tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ".$_POST['txtReload']." WHERE playerID = ".$_SESSION['playerID']." AND preference = 'autoreload'";
				else
					$tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ".$CFG_MINAUTORELOAD." WHERE playerID = ".$_SESSION['playerID']." AND preference = 'autoreload'";

				mysqli_query($dbh, $tmpQuery);
			}

			/* Email Notification */
			if ($CFG_USEEMAILNOTIFICATION)
			{
				$tmpQuery = "UPDATE " . $CFG_TABLE['preferences'] . " SET value = '".$_POST['txtEmailNotification']."' WHERE playerID = ".$_SESSION['playerID']." AND preference = 'emailnotification'";
				mysqli_query($dbh, $tmpQuery);
			}

			/* update current session */
			$_SESSION['pref_history'] = $_POST['rdoHistory'];
			$_SESSION['pref_historylayout'] = $_POST['rdoHistorylayout'];
			$_SESSION['pref_theme'] =  $_POST['rdoTheme'];
      $_SESSION['pref_language'] = $tmpLanguage;

			if (is_numeric($_POST['txtReload']))
			{
				if (intval($_POST['txtReload']) >= $CFG_MINAUTORELOAD)
				{
					$_SESSION['pref_autoreload'] = intval($_POST['txtReload']);
				}
				else
					$_SESSION['pref_autoreload'] = $CFG_MINAUTORELOAD;
			} else
				$_SESSION['pref_autoreload'] = $CFG_MINAUTORELOAD;

			if ($CFG_USEEMAILNOTIFICATION)
				$_SESSION['pref_emailnotification'] = $_POST['txtEmailNotification'];
			break;

		case 'TestEmail':
			if ($CFG_USEEMAILNOTIFICATION)
				webchessMail('test', $_SESSION['pref_emailnotification'], '', '', '');
			break;
                case 'HideMessage':
                        $tmpQuery = "UPDATE " . $CFG_TABLE['communication'] . " SET ack = 1 WHERE commID = " . (int)$_POST['messageID'];
                        mysqli_query($dbh, $tmpQuery);
                        /* set a flash message to be shown after redirect */
                        $_SESSION['flash_msg'] = gettext('Message archived');
                        $_SESSION['flash_type'] = 'success';
                        break;

	}

	/* check session status */
	require 'sessioncheck.php';

	/* set default playing mode to different PCs (as opposed to both players sharing a PC) */
	$_SESSION['isSharedPC'] = false;
?>
<!DOCTYPE html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebChess :: <?php echo gettext("Main Menu");?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/mainmenu.css" type="text/css" />
    <link rel="stylesheet" href="styles/theme.css" type="text/css" />
    <script type="text/javascript" src="javascript/theme.js"></script>
    <script type="text/javascript" src="javascript/tablesort.js"></script>
    <script type="text/javascript" src="javascript/menu.js"></script>
    <script type="text/javascript" src="javascript/messages.js"></script>
     <style>
        body {
            background-color: #f8f9fa;
            transition: background-color 0.3s ease;
        }
        body[data-theme="dark"] {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        .nav-link {
            cursor: pointer;
            font-weight: 500;
        }
        .card {
            margin-bottom: 20px;
            border-radius: 1rem;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        body[data-theme="dark"] .card {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }
        body[data-theme="dark"] .card-header {
            background-color: #1a1a1a !important;
            border-color: #444;
        }
        #navlist .active {
            font-weight: bold;
            background-color: rgba(255,255,255,0.15);
            border-bottom: 3px solid #0d6efd;
            border-radius: 0;
            padding: 0.5rem 1rem;
            color: #fff !important;
        }
        #navlist .nav-link:hover:not(.active) {
            background-color: rgba(255,255,255,0.1);
            border-radius: 0.5rem;
        }
        body[data-theme="dark"] #navlist .active {
            background-color: rgba(255,255,255,0.1);
            border-bottom-color: #0d6efd;
        }
        .section-content {
            display: none;
        }
        .section-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .table {
            margin-bottom: 0;
        }
        body[data-theme="dark"] .table {
            color: #e0e0e0;
            border-color: #444;
        }
        body[data-theme="dark"] .table-light {
            background-color: #3a3a3a !important;
        }
        body[data-theme="dark"] .table-hover tbody tr:hover {
            background-color: #3a3a3a;
        }
        .alert {
            border-radius: 0.75rem;
        }
        body[data-theme="dark"] .alert {
            background-color: #3a3a3a;
            color: #e0e0e0;
            border-color: #555;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #ddd;
        }
        body[data-theme="dark"] .form-control,
        body[data-theme="dark"] .form-select {
            background-color: #3a3a3a;
            color: #e0e0e0;
            border-color: #555;
        }
     </style>
	<script type="text/javascript">
		function validatePersonalInfo()
		{
			if (document.PersonalInfo.txtFirstName.value == ""
				|| document.PersonalInfo.txtLastName.value == ""
			<?php
				/* ToDo: figure out how to check for whitespace only nicks */
				if ($CFG_NICKCHANGEALLOWED)
					echo ('|| document.PersonalInfo.txtNick.value == ""');
			?>
				|| document.PersonalInfo.pwdOldPassword.value == ""
				|| document.PersonalInfo.pwdPassword.value == "")
			{
				alert("Sorry, all personal info fields are required and must be filled out.");
				return;
			}

			if (document.PersonalInfo.pwdPassword.value == document.PersonalInfo.pwdPassword2.value)
				document.PersonalInfo.submit();
			else
				alert("Sorry, the two password fields don't match.  Please try again.");
		}

		function sendResponse(responseType, messageFrom, gameID)
		{
			document.responseToInvite.response.value = responseType;
			document.responseToInvite.messageFrom.value = messageFrom;
			document.responseToInvite.gameID.value = gameID;
			document.responseToInvite.submit();
		}

		function loadGame(gameID)
		{
			if (document.existingGames.rdoShare[0].checked)
				document.existingGames.action = "opponentspassword.php";

			document.existingGames.gameID.value = gameID;
			document.existingGames.submit();
		}

		function withdrawRequest(gameID)
		{
			document.withdrawRequestForm.gameID.value = gameID;
			document.withdrawRequestForm.submit();
		}

		function viewMessage(gameID)
		{
			document.messageViewForm.messageID.value = gameID;
			document.messageViewForm.submit();
		}

		function loadEndedGame(gameID)
		{
			document.endedGames.gameID.value = gameID;
			document.endedGames.submit();
		}
<?php if ($CFG_USEEMAILNOTIFICATION) { ?>
		function testEmail()
		{
			document.userdata.ToDo.value = "TestEmail";
			document.userdata.submit();
		}
<?php } ?>
	function challenge() {
		window.location = 'inviteplayer.php';
	}

	function reload() {
		window.location.reload();
	}

	function logout() {
		document.logOutForm.submit();
	}

	</script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">WEBCHESS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto d-flex align-items-center gap-2" id="navlist">
                <li class="nav-item"><a class="nav-link px-2" href="#continuegame"><?php echo gettext("Active games"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#invitations"><?php echo gettext("Pending challenges"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#messages"><?php echo gettext("Messages"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#challenge"><?php echo gettext("Challenge others"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#viewgame"><?php echo gettext("Replay"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#preferences"><?php echo gettext("Preferences"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#personalinfo"><?php echo gettext("Personal"); ?></a></li>
                <li class="nav-item"><button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" type="button" onclick="toggleTheme()" title="Toggle Dark Mode">🌙</button></li>
                <li class="nav-item"><button class="btn btn-outline-danger btn-sm" type="button" onclick="reload()" title="<?php echo gettext('Reload'); ?>"><?php echo gettext("Reload"); ?></button></li>
                <li class="nav-item"><button class="btn btn-danger btn-sm" type="button" onclick="logout()"><?php echo gettext("Logout"); ?></button></li>
            </ul>
        </div>
    </div>
</nav>

<form name="logOutForm" action="mainmenu.php" method="post">
    <input type="hidden" name="ToDo" value="Logout" />
</form>

<div class="container">
    <div class="row">
        <div class="col-12">
            <?php if ($errMsg != ""): ?>
                <div class="alert alert-danger" role="alert"><?php echo $errMsg; ?></div>
            <?php endif; ?>

            <!-- Active Games -->
            <div id="continuegame" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h5 class="mb-0"><?php echo gettext("Games in Progress");?></h5></div>
                    <div class="card-body">
                        <form name="existingGames" action="chess.php" method="post">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?php echo gettext("Id");?></th>
                                            <th><?php echo gettext("White");?></th>
                                            <th><?php echo gettext("Black");?></th>
                                            <th><?php echo gettext("Mvs");?></th>
                                            <th><?php echo gettext("Current Turn");?></th>
                                            <th><?php echo gettext("Last Move");?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="inProgrTblBdy">
                                        <?php
                                        $tmpGames = mysqli_query($dbh, "SELECT * FROM " . $CFG_TABLE['games'] . " WHERE gameMessage IS NULL AND (whitePlayer = ".(int)$_SESSION['playerID']." OR blackPlayer = ".(int)$_SESSION['playerID'].") ORDER BY dateCreated");
                                        if (mysqli_num_rows($tmpGames) == 0): ?>
                                            <tr><td colspan="6" class="text-center text-muted"><?php echo gettext("You do not currently have any games in progress"); ?></td></tr>
                                        <?php else:
                                            while($tmpGame = mysqli_fetch_assoc($tmpGames)):
                                                $tmpPlayerW = mysqli_query($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ".$tmpGame['whitePlayer']);
                                                $whiteNick = mysqli_fetch_row($tmpPlayerW)[0];
                                                $tmpPlayerB = mysqli_query($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ".$tmpGame['blackPlayer']);
                                                $blackNick = mysqli_fetch_row($tmpPlayerB)[0];
                                                $tmpNumMoves = mysqli_query($dbh, "SELECT COUNT(gameID) FROM " . $CFG_TABLE['history'] . " WHERE gameID = ".$tmpGame['gameID']);
                                                $numMoves = mysqli_fetch_row($tmpNumMoves)[0];
                                                $isWhite = ($tmpGame['whitePlayer'] == $_SESSION['playerID']);
                                                $isMyTurn = (($numMoves % 2 == 0) == $isWhite);
                                        ?>
                                            <tr>
                                                <td><a href="javascript:loadGame(<?php echo $tmpGame['gameID']; ?>)" class="btn btn-sm btn-outline-primary">#<?php echo $tmpGame['gameID']; ?></a></td>
                                                <td><?php echo $whiteNick; ?></td>
                                                <td><?php echo $blackNick; ?></td>
                                                <td><?php echo floor($numMoves / 2); ?></td>
                                                <td><span class="badge <?php echo $isMyTurn ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $isMyTurn ? gettext("Your move") : gettext("Opponent"); ?></span></td>
                                                <td><small><?php echo substr($tmpGame['lastMove'], 0, -3); ?></small></td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 p-3 bg-light rounded border">
                                <label class="form-label d-block fw-bold"><?php echo gettext("Will both players play from the same computer?");?></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" name="rdoShare" type="radio" value="" id="shareYes" />
                                    <label class="form-check-label" for="shareYes"><?php echo gettext("Yes");?></label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" name="rdoShare" type="radio" value="no" id="shareNo" checked="checked" />
                                    <label class="form-check-label" for="shareNo"><?php echo gettext("No");?></label>
                                </div>
                            </div>
                            <input type="hidden" name="gameID" value="" />
                            <input type="hidden" name="sharePC" value="no" />
                        </form>
                        <div class="mt-3 alert alert-warning small">
                            <strong><?php echo gettext("WARNING!");?></strong> <?php echo gettext("Games will expire WITHOUT NOTICE if a move isn't made after") . " " . ($CFG_EXPIREGAME) . " " . gettext("days!");?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invitations -->
            <div id="invitations" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white"><h5 class="mb-0"><?php echo gettext("Challenges");?></h5></div>
                    <div class="card-body">
                        <form name="responseToInvite" action="mainmenu.php" method="post">
                            <h6 class="fw-bold"><?php echo gettext("Challenges from other players");?></h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm">
                                    <thead class="table-light"><tr><th>ID</th><th>White</th><th>Black</th><th>Issued</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $tmpQuery = "SELECT * FROM " . $CFG_TABLE['games'] . " WHERE gameMessage = 'playerInvited' AND ((whitePlayer = ".$_SESSION['playerID']." AND messageFrom = 'black') OR (blackPlayer = ".$_SESSION['playerID']." AND messageFrom = 'white')) ORDER BY dateCreated";
                                        $tmpGames = mysqli_query($dbh, $tmpQuery);
                                        if (mysqli_num_rows($tmpGames) == 0): ?>
                                            <tr><td colspan="5" class="text-center text-muted"><?php echo gettext("You are not currently invited to any games"); ?></td></tr>
                                        <?php else:
                                            while($tmpGame = mysqli_fetch_assoc($tmpGames)):
                                                $tmpFrom = ($tmpGame['whitePlayer'] == $_SESSION['playerID']) ? 'white' : 'black';
                                        ?>
                                            <tr>
                                                <td><?php echo $tmpGame['gameID']; ?></td>
                                                <td><?php echo mysqli_fetch_row(mysqli_query($dbh, "SELECT nick FROM ".$CFG_TABLE['players']." WHERE playerID=".$tmpGame['whitePlayer']))[0]; ?></td>
                                                <td><?php echo mysqli_fetch_row(mysqli_query($dbh, "SELECT nick FROM ".$CFG_TABLE['players']." WHERE playerID=".$tmpGame['blackPlayer']))[0]; ?></td>
                                                <td><small><?php echo substr($tmpGame['dateCreated'], 0, -3); ?></small></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-success" type="button" onclick="sendResponse('accepted', '<?php echo $tmpFrom; ?>', <?php echo $tmpGame['gameID']; ?>)">✓ <?php echo gettext("Accept"); ?></button>
                                                        <button class="btn btn-outline-danger" type="button" onclick="sendResponse('declined', '<?php echo $tmpFrom; ?>', <?php echo $tmpGame['gameID']; ?>)">✕ <?php echo gettext("Decline"); ?></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <input type="hidden" name="response" value="" /><input type="hidden" name="messageFrom" value="" /><input type="hidden" name="gameID" value="" /><input type="hidden" name="ToDo" value="ResponseToInvite" />
                        </form>
                    </div>
                </div>
            </div>

            <!-- Personal Info -->
            <div id="personalinfo" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white"><h5 class="mb-0"><?php echo gettext("Personal information");?></h5></div>
                    <div class="card-body">
                        <form name="PersonalInfo" action="mainmenu.php" method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo gettext("First Name"); ?></label>
                                <input name="txtFirstName" type="text" class="form-control" value="<?php echo($_SESSION['firstName']); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo gettext("Last Name"); ?></label>
                                <input name="txtLastName" type="text" class="form-control" value="<?php echo($_SESSION['lastName']); ?>" />
                            </div>
                            <?php if ($CFG_NICKCHANGEALLOWED): ?>
                            <div class="col-12">
                                <label class="form-label">Nick</label>
                                <input name="txtNick" type="text" class="form-control" value="<?php echo($_SESSION['nick']); ?>" />
                            </div>
                            <?php endif; ?>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo gettext("Current Password"); ?></label>
                                <input name="pwdOldPassword" type="password" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo gettext("New Password"); ?></label>
                                <input name="pwdPassword" type="password" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo gettext("Password Confirmation"); ?></label>
                                <input name="pwdPassword2" type="password" class="form-control" />
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary btn-lg" onclick="validatePersonalInfo()"><i class="bi bi-check-circle"></i> <?php echo gettext("Update");?></button>
                                <input type="hidden" name="ToDo" value="UpdatePersonalInfo" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preferences -->
            <div id="preferences" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white"><h5 class="mb-0"><?php echo gettext("Preferences");?></h5></div>
                    <div class="card-body">
                        <form name="userdata" method="post" action="mainmenu.php" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold"><?php echo gettext("History Format");?></label>
                                <div class="form-check"><input class="form-check-input" name="rdoHistory" type="radio" value="pgn" <?php if ($_SESSION['pref_history'] == 'pgn') echo 'checked'; ?> /> PGN</div>
                                <div class="form-check"><input class="form-check-input" name="rdoHistory" type="radio" value="verbous" <?php if ($_SESSION['pref_history'] != 'pgn') echo 'checked'; ?> /> Verbose</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold"><?php echo gettext("History Layout");?></label>
                                <div class="form-check"><input class="form-check-input" name="rdoHistorylayout" type="radio" value="columns" <?php if ($_SESSION['pref_historylayout'] == 'columns') echo 'checked'; ?> /> Columns</div>
                                <div class="form-check"><input class="form-check-input" name="rdoHistorylayout" type="radio" value="paragraph" <?php if ($_SESSION['pref_historylayout'] != 'columns') echo 'checked'; ?> /> Paragraph</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Theme</label>
                                <select name="rdoTheme" class="form-select">
                                    <option value="beholder" <?php if ($_SESSION['pref_theme'] == 'beholder') echo 'selected'; ?>>Beholder</option>
                                    <option value="gnuchess_fancy" <?php if ($_SESSION['pref_theme'] == 'gnuchess_fancy') echo 'selected'; ?>>GNU Chess Fancy</option>
                                    <option value="gnuchess_simple" <?php if ($_SESSION['pref_theme'] == 'gnuchess_simple') echo 'selected'; ?>>GNU Chess Simple</option>
                                </select>
                            </div>
                              <div class="col-md-4">
                                  <label class="form-label fw-bold">GUI Language</label>
                                  <select name="rdoLanguage" class="form-select">
                                      <option value="en" <?php if (!isset($_SESSION['pref_language']) || $_SESSION['pref_language'] == 'en') echo 'selected'; ?>>EN</option>
                                      <option value="de" <?php if (isset($_SESSION['pref_language']) && $_SESSION['pref_language'] == 'de') echo 'selected'; ?>>DE</option>
                                  </select>
                              </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Auto-reload (sec)</label>
                                <input type="number" class="form-control" name="txtReload" value="<?php echo ($_SESSION['pref_autoreload']); ?>" min="<?php echo $CFG_MINAUTORELOAD; ?>" />
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-secondary btn-lg"><i class="bi bi-sliders"></i> <?php echo gettext("Update");?></button>
                                <input type="hidden" name="ToDo" value="UpdatePrefs" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Other sections (Messages, Challenge, Replay) -->
            <div id="messages" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark"><h5 class="mb-0"><?php echo gettext("Messages");?></h5></div>
                    <div class="card-body">
                         <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo gettext("Send message to player");?></label>
                            <div class="input-group">
                                <select id="player_select" class="form-select">
                                    <?php
                                    $tmpQuery="SELECT playerID, nick FROM " . $CFG_TABLE['players'] . " WHERE playerID <> ".(int)$_SESSION['playerID'];
                                    $tmpPlayers = mysqli_query($dbh, $tmpQuery);
                                    while($tmpPlayer = mysqli_fetch_assoc($tmpPlayers)) {
                                        echo ('<option value="'.$tmpPlayer['playerID'].'"> '.$tmpPlayer['nick']."</option>\n");
                                    }
                                    ?>
                                </select>
                                <button class="btn btn-primary" type="button" onclick="MessagePlayer(document.getElementById('player_select').value)"><i class="bi bi-chat-dots"></i> <?php echo gettext("Open Window");?></button>
                            </div>
                        </div>

                        <h6 class="fw-bold"><?php echo gettext("Current messages");?></h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr><th>Player</th><th>Subject</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $SqlQuery="SELECT * FROM " . $CFG_TABLE['communication'] . " left join " . $CFG_TABLE['players'] . " on " . $CFG_TABLE['communication'] . ".fromID=" . $CFG_TABLE['players'] . ".playerID WHERE ((toID is null) or (toID=" . (int)$_SESSION['playerID'] . ")) and ((fromID is null) or (fromID=playerID)) and ack=0 and gameID is null order by " . $CFG_TABLE['communication'] . ".postDate desc;";
                                    $tmpGames = mysqli_query($dbh, $SqlQuery);
                                    if (mysqli_num_rows($tmpGames) == 0): ?>
                                        <tr><td colspan="3" class="text-center text-muted"><?php echo gettext("No pending messages"); ?></td></tr>
                                    <?php else:
                                        while($tmpGame = mysqli_fetch_assoc($tmpGames)): ?>
                                        <tr>
                                            <td><a href="javascript:viewMessage(<?php echo $tmpGame['commID']; ?>)"><?php echo ($tmpGame['fromID']!=0?$tmpGame['nick']:"Webchess"); ?></a></td>
                                            <td><?php echo (strlen($tmpGame['title'])>40? substr($tmpGame['title'],0,37)."..." : $tmpGame['title']); ?></td>
                                            <td><small><?php echo $tmpGame['postDate']; ?></small></td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="challenge" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white"><h5 class="mb-0"><?php echo gettext("Issue a challenge");?></h5></div>
                    <div class="card-body">
                        <form name="newchallenge" action="mainmenu.php" method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo gettext("Select Opponent");?></label>
                                <select name="opponent" class="form-select">
                                    <?php
                                    $tmpPlayers = mysqli_query($dbh, "SELECT playerID, nick FROM " . $CFG_TABLE['players'] . " WHERE playerID <> ".(int)$_SESSION['playerID']);
                                    while($tmpPlayer = mysqli_fetch_assoc($tmpPlayers)) {
                                        echo ('<option value="'.$tmpPlayer['playerID'].'"> '.$tmpPlayer['nick']."</option>\n");
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo gettext("Your Color");?></label>
                                <div class="mt-2">
                                    <div class="form-check form-check-inline"><input class="form-check-input" name="color" type="radio" value="random" checked /> Random</div>
                                    <div class="form-check form-check-inline"><input class="form-check-input" name="color" type="radio" value="white" /> White</div>
                                    <div class="form-check form-check-inline"><input class="form-check-input" name="color" type="radio" value="black" /> Black</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-suit-heart"></i> <?php echo gettext("Invite");?></button>
                                <input type="hidden" name="ToDo" value="InvitePlayer" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="viewgame" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h5 class="mb-0"><?php echo gettext("View finished games");?></h5></div>
                    <div class="card-body">
                         <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr><th>ID</th><th>White</th><th>Black</th><th>Mvs</th><th>Result</th><th>Last Move</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $tmpGames = mysqli_query($dbh, "SELECT * FROM " . $CFG_TABLE['games'] . " WHERE (gameMessage <> '' AND gameMessage <> 'playerInvited' AND gameMessage <> 'inviteDeclined') AND (whitePlayer = ".(int)$_SESSION['playerID']." OR blackPlayer = ".(int)$_SESSION['playerID'].") ORDER BY lastMove DESC");
                                    if (mysqli_num_rows($tmpGames) == 0): ?>
                                        <tr><td colspan="6" class="text-center text-muted"><?php echo gettext("No finished games found"); ?></td></tr>
                                    <?php else:
                                        while($tmpGame = mysqli_fetch_assoc($tmpGames)):
                                            $tmpNumMoves = mysqli_fetch_row(mysqli_query($dbh, "SELECT COUNT(gameID) FROM " . $CFG_TABLE['history'] . " WHERE gameID = ".$tmpGame['gameID']))[0];
                                    ?>
                                        <tr>
                                            <td><a href="javascript:loadGame(<?php echo $tmpGame['gameID']; ?>)" class="btn btn-xs btn-outline-success">#<?php echo $tmpGame['gameID']; ?></a></td>
                                            <td><?php echo mysqli_fetch_row(mysqli_query($dbh, "SELECT nick FROM ".$CFG_TABLE['players']." WHERE playerID=".$tmpGame['whitePlayer']))[0]; ?></td>
                                            <td><?php echo mysqli_fetch_row(mysqli_query($dbh, "SELECT nick FROM ".$CFG_TABLE['players']." WHERE playerID=".$tmpGame['blackPlayer']))[0]; ?></td>
                                            <td><?php echo floor($tmpNumMoves / 2); ?></td>
                                            <td><small><?php echo $tmpGame['gameMessage']; ?></small></td>
                                            <td><small><?php echo substr($tmpGame['lastMove'], 0, -3); ?></small></td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

             <div class="alert alert-info text-center small mt-5">
                WebChess Version 2.0.0 &bull; <a href="https://github.com/francwalter/webchess/" class="alert-link">WebChess - Github</a>
            </div>
        </div>
    </div>
</div>

<!-- Forms for actions -->
<form name="messageViewForm" method="post" action="viewmessage.php"><input type="hidden" name="messageID" value="" /></form>
<form name="endedGames" action="chess.php" method="post"><input type="hidden" name="gameID" value="" /><input type="hidden" name="sharePC" value="no" /></form>
<form name="withdrawRequestForm" action="mainmenu.php" method="post"><input type="hidden" name="gameID" value="" /><input type="hidden" name="ToDo" value="WithdrawRequest" /></form>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php mysqli_close($dbh); ?>
