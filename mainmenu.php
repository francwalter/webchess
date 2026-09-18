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

  require_once 'security.php';

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
  require 'csrf.php';


	/* allow WebChess to be run on PHP systems < 4.1.0, using old http vars */
	fixOldPHPVersions();

	/* if this page is accessed directly (ie: without going through login), */
	/* player is logged off by default */
	if (!isset($_SESSION['playerID']))
		$_SESSION['playerID'] = -1;

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !webchessCsrfValidateRequest())
  {
    if ($_SESSION['playerID'] > 0)
    {
      $_SESSION['flash_msg'] = webchessTranslate('Your session form token expired. Please reload the page and try again.');
      $_SESSION['flash_type'] = 'danger';
      header('Location: mainmenu.php');
      exit();
    }

    die(webchessTranslate('Your session form token expired. Please reload the page and try again.'));
  }

	/* connect to database */
	require 'connectdb.php';

	/* cleanup dead games */
	/* determine threshold for oldest game permitted */
	$targetDate = date("Y-m-d", mktime(0,0,0, date('m'), date('d') - $CFG_EXPIREGAME, date('Y')));

  /* find out which games are older */
  $tmpOldGames = false;
  $stmtOldGames = mysqli_prepare($dbh, "SELECT gameID FROM " . $CFG_TABLE['games'] . " WHERE lastMove < ?");
  if ($stmtOldGames)
  {
    mysqli_stmt_bind_param($stmtOldGames, "s", $targetDate);
    mysqli_stmt_execute($stmtOldGames);
    $tmpOldGames = mysqli_stmt_get_result($stmtOldGames);
    mysqli_stmt_close($stmtOldGames);
  }

  $stmtDeleteHistory = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['history'] . " WHERE gameID = ?");
  $stmtDeletePieces = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['pieces'] . " WHERE gameID = ?");
  $stmtDeleteMessages = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ?");
  $stmtDeleteGame = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");

	/* for each older game... */
  while($tmpOldGames && ($tmpOldGame = mysqli_fetch_assoc($tmpOldGames)))
	{
    $oldGameId = (int)$tmpOldGame['gameID'];
		/* ... clear the history... */
    if ($stmtDeleteHistory)
    {
      mysqli_stmt_bind_param($stmtDeleteHistory, "i", $oldGameId);
      mysqli_stmt_execute($stmtDeleteHistory);
    }

		/* ... and the board... */
    if ($stmtDeletePieces)
    {
      mysqli_stmt_bind_param($stmtDeletePieces, "i", $oldGameId);
      mysqli_stmt_execute($stmtDeletePieces);
    }

		/* ... and the messages... */
    if ($stmtDeleteMessages)
    {
      mysqli_stmt_bind_param($stmtDeleteMessages, "i", $oldGameId);
      mysqli_stmt_execute($stmtDeleteMessages);
    }

		/* ... and finally the game itself from the database */
    if ($stmtDeleteGame)
    {
      mysqli_stmt_bind_param($stmtDeleteGame, "i", $oldGameId);
      mysqli_stmt_execute($stmtDeleteGame);
    }
	}

  if ($stmtDeleteHistory)
    mysqli_stmt_close($stmtDeleteHistory);
  if ($stmtDeletePieces)
    mysqli_stmt_close($stmtDeletePieces);
  if ($stmtDeleteMessages)
    mysqli_stmt_close($stmtDeleteMessages);
  if ($stmtDeleteGame)
    mysqli_stmt_close($stmtDeleteGame);

	$tmpNewUser = false;
	$errMsg = "";
  $redirectTo = '';
	$toDo = isset($_POST['ToDo']) ? $_POST['ToDo'] : '';

  function webchessGetNickByPlayerId($dbh, $tablePlayers, $playerId)
  {
    $nick = '';
    $stmt = mysqli_prepare($dbh, "SELECT nick FROM " . $tablePlayers . " WHERE playerID = ? LIMIT 1");
    if ($stmt)
    {
      $playerId = (int)$playerId;
      mysqli_stmt_bind_param($stmt, "i", $playerId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_row($res) : null;
      if ($row)
        $nick = (string)$row[0];
      mysqli_stmt_close($stmt);
    }
    return $nick;
  }

  function webchessCountMovesByGameId($dbh, $tableHistory, $gameId)
  {
    $count = 0;
    $stmt = mysqli_prepare($dbh, "SELECT COUNT(gameID) FROM " . $tableHistory . " WHERE gameID = ?");
    if ($stmt)
    {
      $gameId = (int)$gameId;
      mysqli_stmt_bind_param($stmt, "i", $gameId);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_row($res) : null;
      if ($row)
        $count = (int)$row[0];
      mysqli_stmt_close($stmt);
    }
    return $count;
  }

  function webchessLoginThrottleKey($nick)
  {
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'unknown';
    $nick = strtolower(trim((string)$nick));
    return hash('sha256', $remoteAddr . '|' . $nick);
  }

  function webchessLoginThrottleFilePath()
  {
    $baseDir = sys_get_temp_dir();
    if (!is_string($baseDir) || $baseDir === '')
      $baseDir = __DIR__;

    $throttleDir = rtrim($baseDir, "\\/") . DIRECTORY_SEPARATOR . 'webchess';
    if (!is_dir($throttleDir))
      @mkdir($throttleDir, 0700, true);

    return $throttleDir . DIRECTORY_SEPARATOR . 'login_throttle.json';
  }

  function webchessLoginThrottleReadData($fp)
  {
    rewind($fp);
    $json = stream_get_contents($fp);
    if (!is_string($json) || $json === '')
      return array();

    $data = json_decode($json, true);
    return is_array($data) ? $data : array();
  }

  function webchessLoginThrottleWriteData($fp, $data)
  {
    if (!is_array($data))
      $data = array();

    $json = json_encode($data);
    if (!is_string($json))
      $json = '{}';

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
  }

  function webchessLoginThrottleGc(&$data, $now, $windowSeconds, $lockSeconds)
  {
    if (!is_array($data))
      $data = array();

    foreach ($data as $key => $entry)
    {
      $firstAttempt = isset($entry['firstAttempt']) ? (int)$entry['firstAttempt'] : 0;
      $blockedUntil = isset($entry['blockedUntil']) ? (int)$entry['blockedUntil'] : 0;
      $lastRelevantTs = max($firstAttempt, $blockedUntil);
      if (($now - $lastRelevantTs) > ($windowSeconds + $lockSeconds))
        unset($data[$key]);
    }
  }

  function webchessIsLoginBlocked($nick, &$retryAfterSeconds = 0)
  {
    $maxAttempts = 5;
    $windowSeconds = 15 * 60;
    $lockSeconds = 15 * 60;
    $key = webchessLoginThrottleKey($nick);
    $now = time();
    $retryAfterSeconds = 0;

    $fp = @fopen(webchessLoginThrottleFilePath(), 'c+');
    if (!$fp)
      return false;
    if (!flock($fp, LOCK_EX))
    {
      fclose($fp);
      return false;
    }

    $data = webchessLoginThrottleReadData($fp);
    webchessLoginThrottleGc($data, $now, $windowSeconds, $lockSeconds);

    if (!isset($data[$key]))
    {
      webchessLoginThrottleWriteData($fp, $data);
      flock($fp, LOCK_UN);
      fclose($fp);
      return false;
    }

    $entry = $data[$key];
    $firstAttempt = isset($entry['firstAttempt']) ? (int)$entry['firstAttempt'] : 0;
    $failedCount = isset($entry['failedCount']) ? (int)$entry['failedCount'] : 0;
    $blockedUntil = isset($entry['blockedUntil']) ? (int)$entry['blockedUntil'] : 0;

    if ($blockedUntil > $now)
    {
      $retryAfterSeconds = $blockedUntil - $now;
      webchessLoginThrottleWriteData($fp, $data);
      flock($fp, LOCK_UN);
      fclose($fp);
      return true;
    }

    if (($now - $firstAttempt) > $windowSeconds)
    {
      unset($data[$key]);
      webchessLoginThrottleWriteData($fp, $data);
      flock($fp, LOCK_UN);
      fclose($fp);
      return false;
    }

    if ($failedCount >= $maxAttempts)
    {
      $data[$key]['blockedUntil'] = $now + $lockSeconds;
      $retryAfterSeconds = $lockSeconds;
      webchessLoginThrottleWriteData($fp, $data);
      flock($fp, LOCK_UN);
      fclose($fp);
      return true;
    }

    webchessLoginThrottleWriteData($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);
    return false;
  }

  function webchessRecordLoginFailure($nick)
  {
    $key = webchessLoginThrottleKey($nick);
    $now = time();
    $maxAttempts = 5;
    $windowSeconds = 15 * 60;

    $fp = @fopen(webchessLoginThrottleFilePath(), 'c+');
    if (!$fp)
      return;
    if (!flock($fp, LOCK_EX))
    {
      fclose($fp);
      return;
    }

    $data = webchessLoginThrottleReadData($fp);
    webchessLoginThrottleGc($data, $now, $windowSeconds, 15 * 60);

    if (!isset($data[$key]) || ($now - (int)$data[$key]['firstAttempt']) > $windowSeconds)
    {
      $data[$key] = array(
        'firstAttempt' => $now,
        'failedCount' => 1,
        'blockedUntil' => 0,
      );
      webchessLoginThrottleWriteData($fp, $data);
      flock($fp, LOCK_UN);
      fclose($fp);
      return;
    }

    $data[$key]['failedCount'] = (int)$data[$key]['failedCount'] + 1;
    if ((int)$data[$key]['failedCount'] >= $maxAttempts)
      $data[$key]['blockedUntil'] = $now + (15 * 60);

    webchessLoginThrottleWriteData($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);
  }

  function webchessClearLoginThrottle($nick)
  {
    $key = webchessLoginThrottleKey($nick);

    $fp = @fopen(webchessLoginThrottleFilePath(), 'c+');
    if (!$fp)
      return;
    if (!flock($fp, LOCK_EX))
    {
      fclose($fp);
      return;
    }

    $data = webchessLoginThrottleReadData($fp);
    if (isset($data[$key]))
      unset($data[$key]);

    webchessLoginThrottleWriteData($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);
  }

	switch($toDo)
	{
		case 'NewUser':
			/* create new player */
			$tmpNewUser = true;

			/* sanity check: empty nick */
			if ($_POST['txtNick'] == "")
				die("ERROR: must supply a valid nick!");

      /* check for existing user with same nick */
      $existingUsers = null;
      $stmtExisting = mysqli_prepare($dbh, "SELECT playerID FROM " . $CFG_TABLE['players'] . " WHERE nick = ?");
      if ($stmtExisting)
      {
        mysqli_stmt_bind_param($stmtExisting, "s", $_POST['txtNick']);
        mysqli_stmt_execute($stmtExisting);
        $existingUsers = mysqli_stmt_get_result($stmtExisting);
        mysqli_stmt_close($stmtExisting);
      }
      if ($existingUsers && mysqli_num_rows($existingUsers) > 0)
			{
				require 'newuser.php';
				die();
			}

      $newPasswordHash = password_hash((string)$_POST['pwdPassword'], PASSWORD_DEFAULT);
      $stmtNewUser = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['players'] . " (password, firstName, lastName, nick) VALUES (?, ?, ?, ?)");
      if (!$stmtNewUser)
        die("ERROR: could not create user.");
      mysqli_stmt_bind_param($stmtNewUser, "ssss", $newPasswordHash, $_POST['txtFirstName'], $_POST['txtLastName'], $_POST['txtNick']);
      mysqli_stmt_execute($stmtNewUser);
      mysqli_stmt_close($stmtNewUser);

			/* get ID of new player */
			$_SESSION['playerID'] = mysqli_insert_id($dbh);

      $prefHistory = (isset($_POST['rdoHistory']) && $_POST['rdoHistory'] === 'verbous') ? 'verbous' : 'pgn';
      $prefHistoryLayout = (isset($_POST['rdoHistorylayout']) && $_POST['rdoHistorylayout'] === 'paragraph') ? 'paragraph' : 'columns';
      $allowedThemes = array('beholder', 'gnuchess_simple', 'gnuchess_fancy', 'plain');
      $prefTheme = isset($_POST['rdoTheme']) && in_array($_POST['rdoTheme'], $allowedThemes, true) ? $_POST['rdoTheme'] : 'beholder';

      /* set History format preference */
      $stmtPrefInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, 'history', ?)");
      if ($stmtPrefInsert)
      {
        $playerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtPrefInsert, "is", $playerId, $prefHistory);
        mysqli_stmt_execute($stmtPrefInsert);
        mysqli_stmt_close($stmtPrefInsert);
      }

      /* set History layout preference */
      $stmtPrefInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, 'historylayout', ?)");
      if ($stmtPrefInsert)
      {
        $playerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtPrefInsert, "is", $playerId, $prefHistoryLayout);
        mysqli_stmt_execute($stmtPrefInsert);
        mysqli_stmt_close($stmtPrefInsert);
      }

      /* set Theme preference */
      $stmtPrefInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, 'theme', ?)");
      if ($stmtPrefInsert)
      {
        $playerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtPrefInsert, "is", $playerId, $prefTheme);
        mysqli_stmt_execute($stmtPrefInsert);
        mysqli_stmt_close($stmtPrefInsert);
      }

      /* set GUI language preference */
      $tmpLanguage = (isset($_POST['rdoLanguage']) && ($_POST['rdoLanguage'] == 'de')) ? 'de' : 'en';
      $stmtPrefInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, 'language', ?)");
      if ($stmtPrefInsert)
      {
        $playerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtPrefInsert, "is", $playerId, $tmpLanguage);
        mysqli_stmt_execute($stmtPrefInsert);
        mysqli_stmt_close($stmtPrefInsert);
      }

      /* set auto-reload preference */
      $reloadValue = $CFG_MINAUTORELOAD;
      if (isset($_POST['txtReload']) && is_numeric($_POST['txtReload']) && intval($_POST['txtReload']) >= $CFG_MINAUTORELOAD)
        $reloadValue = intval($_POST['txtReload']);

      $stmtPrefInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, 'autoreload', ?)");
      if ($stmtPrefInsert)
      {
        $playerId = (int)$_SESSION['playerID'];
        $reloadValueStr = (string)$reloadValue;
        mysqli_stmt_bind_param($stmtPrefInsert, "is", $playerId, $reloadValueStr);
        mysqli_stmt_execute($stmtPrefInsert);
        mysqli_stmt_close($stmtPrefInsert);
      }

			/* set email notification preference */
			if ($CFG_USEEMAILNOTIFICATION)
			{
        $tmpEmailNotification = trim((string)($_POST['txtEmailNotification'] ?? ''));
        if ($tmpEmailNotification !== '' && !filter_var($tmpEmailNotification, FILTER_VALIDATE_EMAIL))
          $tmpEmailNotification = '';

        $stmtPrefInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, 'emailnotification', ?)");
        if ($stmtPrefInsert)
        {
          $playerId = (int)$_SESSION['playerID'];
          mysqli_stmt_bind_param($stmtPrefInsert, "is", $playerId, $tmpEmailNotification);
          mysqli_stmt_execute($stmtPrefInsert);
          mysqli_stmt_close($stmtPrefInsert);
        }
			}

			/* no break, login user */
      webchessClearLoginThrottle(isset($_POST['txtNick']) ? (string)$_POST['txtNick'] : '');

		case 'Login':
        $loginNick = isset($_POST['txtNick']) ? (string)$_POST['txtNick'] : '';
        $retryAfterSeconds = 0;
        if (webchessIsLoginBlocked($loginNick, $retryAfterSeconds))
        {
          $retryAfterSeconds = max(1, (int)$retryAfterSeconds);
          $lockoutMessage = sprintf(webchessTranslate('Too many failed login attempts. Please try again in %d seconds.'), $retryAfterSeconds);
          echo "<script>alert('" . addslashes($lockoutMessage) . "'); window.location.replace('index.php');</script>\n";
          exit();
        }

        /* check for a player with supplied nick and verify password */
        $tmpPlayer = null;
        $stmtLogin = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['players'] . " WHERE nick = ? LIMIT 1");
        if ($stmtLogin)
        {
          mysqli_stmt_bind_param($stmtLogin, "s", $loginNick);
          mysqli_stmt_execute($stmtLogin);
          $tmpPlayers = mysqli_stmt_get_result($stmtLogin);
          $tmpPlayer = $tmpPlayers ? mysqli_fetch_assoc($tmpPlayers) : null;
          mysqli_stmt_close($stmtLogin);
        }

      /* if such a player exists, log him in... otherwise die */
        if ($tmpPlayer && webchessPasswordMatches((string)$_POST['pwdPassword'], (string)$tmpPlayer['password']))
      {
              webchessClearLoginThrottle($loginNick);
          session_regenerate_id(true);
          webchessCsrfRegenerateToken();
        $_SESSION['playerID'] = $tmpPlayer['playerID'];
        $_SESSION['lastInputTime'] = time();
        $_SESSION['playerName'] = $tmpPlayer['firstName']." ".$tmpPlayer['lastName'];
        $_SESSION['firstName'] = $tmpPlayer['firstName'];
        $_SESSION['lastName'] = $tmpPlayer['lastName'];
        $_SESSION['nick'] = $tmpPlayer['nick'];

          /* Seamless migration of legacy/plaintext passwords. */
          if (webchessPasswordNeedsRehash((string)$tmpPlayer['password']))
          {
            $newLoginHash = password_hash((string)$_POST['pwdPassword'], PASSWORD_DEFAULT);
            $stmtRehash = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['players'] . " SET password = ? WHERE playerID = ?");
            if ($stmtRehash)
            {
              $playerIdInt = (int)$tmpPlayer['playerID'];
              mysqli_stmt_bind_param($stmtRehash, "si", $newLoginHash, $playerIdInt);
              mysqli_stmt_execute($stmtRehash);
              mysqli_stmt_close($stmtRehash);
            }
          }
      }
      else {
        webchessRecordLoginFailure($loginNick);
        echo "<script>alert('Invalid Nick or Password. Please try again'); window.location.replace('index.php');</script>\n";
        exit();
      }

      /* load user preferences */
      $tmpPreferences = false;
      $stmtPrefs = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ?");
      if ($stmtPrefs)
      {
        $playerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtPrefs, "i", $playerId);
        mysqli_stmt_execute($stmtPrefs);
        $tmpPreferences = mysqli_stmt_get_result($stmtPrefs);
        mysqli_stmt_close($stmtPrefs);
      }

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
        $stmtMissingPref = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['preferences'] . " (playerID, preference, value) VALUES (?, ?, ?)");
        if ($stmtMissingPref)
        {
          $playerId = (int)$_SESSION['playerID'];
          $defaultValue = (string)$defaultValue;
          mysqli_stmt_bind_param($stmtMissingPref, "iss", $playerId, $missingPref, $defaultValue);
          mysqli_stmt_execute($stmtMissingPref);
          mysqli_stmt_close($stmtMissingPref);
        }

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
      $opponentId = isset($_POST['opponent']) ? (int)$_POST['opponent'] : 0;
      if ($opponentId <= 0 || $opponentId === (int)$_SESSION['playerID'])
        break;

      $requestedColor = isset($_POST['color']) ? (string)$_POST['color'] : 'random';
      if (!in_array($requestedColor, array('random', 'white', 'black'), true))
        $requestedColor = 'random';

			/* prevent multiple pending requests between two players with the same originator */
      $stmtExistingRequests = mysqli_prepare($dbh, "SELECT gameID FROM " . $CFG_TABLE['games'] . " WHERE gameMessage = 'playerInvited' AND ((messageFrom = 'white' AND whitePlayer = ? AND blackPlayer = ?) OR (messageFrom = 'black' AND whitePlayer = ? AND blackPlayer = ?))");
      $tmpExistingRequests = false;
      if ($stmtExistingRequests)
      {
        $playerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtExistingRequests, "iiii", $playerId, $opponentId, $opponentId, $playerId);
        mysqli_stmt_execute($stmtExistingRequests);
        $tmpExistingRequests = mysqli_stmt_get_result($stmtExistingRequests);
        mysqli_stmt_close($stmtExistingRequests);
      }

      if (!$tmpExistingRequests || mysqli_num_rows($tmpExistingRequests) == 0)
			{
				if (!minimum_version("4.2.0"))
					init_srand();

        if ($requestedColor == 'random')
					$tmpColor = (mt_rand(0,1) == 1) ? "white" : "black";
				else
          $tmpColor = $requestedColor;

        $whitePlayerId = ($tmpColor === 'white') ? (int)$_SESSION['playerID'] : $opponentId;
        $blackPlayerId = ($tmpColor === 'white') ? $opponentId : (int)$_SESSION['playerID'];

        $stmtInvite = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['games'] . " (whitePlayer, blackPlayer, gameMessage, messageFrom, dateCreated, lastMove) VALUES (?, ?, 'playerInvited', ?, NOW(), NOW())");
        if ($stmtInvite)
        {
          mysqli_stmt_bind_param($stmtInvite, "iis", $whitePlayerId, $blackPlayerId, $tmpColor);
          mysqli_stmt_execute($stmtInvite);
          mysqli_stmt_close($stmtInvite);
        }

				/* if email notification is activated... */
				if ($CFG_USEEMAILNOTIFICATION)
				{
					/* if opponent is using email notification... */
          $stmtOpponentEmail = mysqli_prepare($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ? AND preference = 'emailNotification'");
          $tmpOpponentEmail = false;
          if ($stmtOpponentEmail)
          {
            mysqli_stmt_bind_param($stmtOpponentEmail, "i", $opponentId);
            mysqli_stmt_execute($stmtOpponentEmail);
            $tmpOpponentEmail = mysqli_stmt_get_result($stmtOpponentEmail);
            mysqli_stmt_close($stmtOpponentEmail);
          }
          if ($tmpOpponentEmail && mysqli_num_rows($tmpOpponentEmail) > 0)
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
      $gameId = isset($_POST['gameID']) ? (int)$_POST['gameID'] : 0;
      $response = isset($_POST['response']) ? (string)$_POST['response'] : '';
      if ($gameId <= 0 || !in_array($response, array('accepted', 'declined'), true))
        break;

      $stmtInviteGame = mysqli_prepare($dbh, "SELECT whitePlayer, blackPlayer, messageFrom, gameMessage FROM " . $CFG_TABLE['games'] . " WHERE gameID = ? LIMIT 1");
      if (!$stmtInviteGame)
        break;
      mysqli_stmt_bind_param($stmtInviteGame, "i", $gameId);
      mysqli_stmt_execute($stmtInviteGame);
      $inviteGameRes = mysqli_stmt_get_result($stmtInviteGame);
      $inviteGame = $inviteGameRes ? mysqli_fetch_assoc($inviteGameRes) : null;
      mysqli_stmt_close($stmtInviteGame);
      if (!$inviteGame || $inviteGame['gameMessage'] !== 'playerInvited')
        break;

      $invitedPlayerId = ($inviteGame['messageFrom'] === 'white') ? (int)$inviteGame['blackPlayer'] : (int)$inviteGame['whitePlayer'];
      if ($invitedPlayerId !== (int)$_SESSION['playerID'])
        break;

      if ($response == 'accepted')
			{
				/* update game data */
        $stmtAcceptInvite = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = NULL, messageFrom = NULL WHERE gameID = ?");
        if ($stmtAcceptInvite)
        {
          mysqli_stmt_bind_param($stmtAcceptInvite, "i", $gameId);
          mysqli_stmt_execute($stmtAcceptInvite);
          mysqli_stmt_close($stmtAcceptInvite);
        }

				/* setup new board */
        $_SESSION['gameID'] = $gameId;
        createNewGame($gameId);
				saveGame();
			}
			else
			{

        $stmtDeclineInvite = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = 'inviteDeclined', messageFrom = ? WHERE gameID = ?");
        if ($stmtDeclineInvite)
        {
          $inviterColor = (string)$inviteGame['messageFrom'];
          mysqli_stmt_bind_param($stmtDeclineInvite, "si", $inviterColor, $gameId);
          mysqli_stmt_execute($stmtDeclineInvite);
          mysqli_stmt_close($stmtDeclineInvite);
        }
			}

			break;

		case 'WithdrawRequest':
      $gameId = isset($_POST['gameID']) ? (int)$_POST['gameID'] : 0;
      if ($gameId <= 0)
        break;

      /* get game and ensure current user is the inviter of a pending invite */
      $stmtGame = mysqli_prepare($dbh, "SELECT whitePlayer, blackPlayer, messageFrom, gameMessage FROM " . $CFG_TABLE['games'] . " WHERE gameID = ? LIMIT 1");
      if (!$stmtGame)
        break;
      mysqli_stmt_bind_param($stmtGame, "i", $gameId);
      mysqli_stmt_execute($stmtGame);
      $tmpGameRes = mysqli_stmt_get_result($stmtGame);
      $tmpGame = $tmpGameRes ? mysqli_fetch_assoc($tmpGameRes) : null;
      mysqli_stmt_close($stmtGame);

      if ($tmpGame && $tmpGame['gameMessage'] === 'playerInvited')
			{
        $inviterId = ($tmpGame['messageFrom'] === 'white') ? (int)$tmpGame['whitePlayer'] : (int)$tmpGame['blackPlayer'];
        if ($inviterId !== (int)$_SESSION['playerID'])
          break;
        $opponentID = ($inviterId === (int)$tmpGame['whitePlayer']) ? (int)$tmpGame['blackPlayer'] : (int)$tmpGame['whitePlayer'];

        $stmtDeleteInvite = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
        if ($stmtDeleteInvite)
				{
          mysqli_stmt_bind_param($stmtDeleteInvite, "i", $gameId);
          mysqli_stmt_execute($stmtDeleteInvite);
          mysqli_stmt_close($stmtDeleteInvite);
				}

				/* if email notification is activated... */
				if ($CFG_USEEMAILNOTIFICATION)
				{
					/* if opponent is using email notification... */
          $stmtOpponentEmail = mysqli_prepare($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ? AND preference = 'emailNotification'");
          $tmpOpponentEmail = false;
          if ($stmtOpponentEmail)
          {
            mysqli_stmt_bind_param($stmtOpponentEmail, "i", $opponentID);
            mysqli_stmt_execute($stmtOpponentEmail);
            $tmpOpponentEmail = mysqli_stmt_get_result($stmtOpponentEmail);
            mysqli_stmt_close($stmtOpponentEmail);
          }
          if ($tmpOpponentEmail && mysqli_num_rows($tmpOpponentEmail) > 0)
					{
						$opponentEmail = mysqli_fetch_row($tmpOpponentEmail)[0];
						if ($opponentEmail != '')
						{
							/* notify opponent of invitation via email */
              webchessMail('withdrawal', $opponentEmail, '', $_SESSION['nick'], $gameId);
						}
					}
				}
			}
			break;

		case 'UpdatePersonalInfo':
      $dbPassword = null;
      $stmtCurrentPwd = mysqli_prepare($dbh, "SELECT password FROM " . $CFG_TABLE['players'] . " WHERE playerID = ?");
      if ($stmtCurrentPwd)
      {
        $sessionPlayerId = (int)$_SESSION['playerID'];
        mysqli_stmt_bind_param($stmtCurrentPwd, "i", $sessionPlayerId);
        mysqli_stmt_execute($stmtCurrentPwd);
        $tmpPassword = mysqli_stmt_get_result($stmtCurrentPwd);
        $tmpPasswordRow = $tmpPassword ? mysqli_fetch_row($tmpPassword) : null;
        $dbPassword = $tmpPasswordRow ? $tmpPasswordRow[0] : null;
        mysqli_stmt_close($stmtCurrentPwd);
      }

      if (!webchessPasswordMatches((string)$_POST['pwdOldPassword'], (string)$dbPassword))
				$errMsg = "Sorry, incorrect old password!";
			else
			{
				$tmpDoUpdate = true;

				if ($CFG_NICKCHANGEALLOWED)
				{
          $existingUsers = null;
          $stmtNickExists = mysqli_prepare($dbh, "SELECT playerID FROM " . $CFG_TABLE['players'] . " WHERE nick = ? AND playerID <> ?");
          if ($stmtNickExists)
          {
            $sessionPlayerId = (int)$_SESSION['playerID'];
            mysqli_stmt_bind_param($stmtNickExists, "si", $_POST['txtNick'], $sessionPlayerId);
            mysqli_stmt_execute($stmtNickExists);
            $existingUsers = mysqli_stmt_get_result($stmtNickExists);
            mysqli_stmt_close($stmtNickExists);
          }

          if ($existingUsers && mysqli_num_rows($existingUsers) > 0)
					{
						$errMsg = "Sorry, that nick is already in use.";
						$tmpDoUpdate = false;
					}
				}

				if ($tmpDoUpdate)
				{
          /* update DB */
          $newProfileHash = password_hash((string)$_POST['pwdPassword'], PASSWORD_DEFAULT);
          $sessionPlayerId = (int)$_SESSION['playerID'];
          if ($CFG_NICKCHANGEALLOWED && $_POST['txtNick'] != "")
          {
            $stmtUpdateProfile = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['players'] . " SET firstName = ?, lastName = ?, password = ?, nick = ? WHERE playerID = ?");
            if ($stmtUpdateProfile)
            {
              mysqli_stmt_bind_param($stmtUpdateProfile, "ssssi", $_POST['txtFirstName'], $_POST['txtLastName'], $newProfileHash, $_POST['txtNick'], $sessionPlayerId);
              mysqli_stmt_execute($stmtUpdateProfile);
              mysqli_stmt_close($stmtUpdateProfile);
            }
          }
          else
          {
            $stmtUpdateProfile = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['players'] . " SET firstName = ?, lastName = ?, password = ? WHERE playerID = ?");
            if ($stmtUpdateProfile)
            {
              mysqli_stmt_bind_param($stmtUpdateProfile, "sssi", $_POST['txtFirstName'], $_POST['txtLastName'], $newProfileHash, $sessionPlayerId);
              mysqli_stmt_execute($stmtUpdateProfile);
              mysqli_stmt_close($stmtUpdateProfile);
            }
          }

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
      $oldLanguage = $_SESSION['pref_language'] ?? 'en';

      $prefHistory = (isset($_POST['rdoHistory']) && $_POST['rdoHistory'] === 'verbous') ? 'verbous' : 'pgn';
      $prefHistoryLayout = (isset($_POST['rdoHistorylayout']) && $_POST['rdoHistorylayout'] === 'paragraph') ? 'paragraph' : 'columns';
      $allowedThemes = array('beholder', 'gnuchess_simple', 'gnuchess_fancy');
      $prefTheme = isset($_POST['rdoTheme']) && in_array($_POST['rdoTheme'], $allowedThemes, true) ? $_POST['rdoTheme'] : 'beholder';
      $sessionPlayerId = (int)$_SESSION['playerID'];

      /* Theme */
      $stmtPrefUpdate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ? WHERE playerID = ? AND preference = 'theme'");
      if ($stmtPrefUpdate)
      {
        mysqli_stmt_bind_param($stmtPrefUpdate, "si", $prefTheme, $sessionPlayerId);
        mysqli_stmt_execute($stmtPrefUpdate);
        mysqli_stmt_close($stmtPrefUpdate);
      }

      /* GUI Language */
      $tmpLanguage = (isset($_POST['rdoLanguage']) && ($_POST['rdoLanguage'] == 'de')) ? 'de' : 'en';
      $stmtPrefUpdate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ? WHERE playerID = ? AND preference = 'language'");
      if ($stmtPrefUpdate)
      {
        mysqli_stmt_bind_param($stmtPrefUpdate, "si", $tmpLanguage, $sessionPlayerId);
        mysqli_stmt_execute($stmtPrefUpdate);
        mysqli_stmt_close($stmtPrefUpdate);
      }

      /* History format */
      $stmtPrefUpdate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ? WHERE playerID = ? AND preference = 'history'");
      if ($stmtPrefUpdate)
      {
        mysqli_stmt_bind_param($stmtPrefUpdate, "si", $prefHistory, $sessionPlayerId);
        mysqli_stmt_execute($stmtPrefUpdate);
        mysqli_stmt_close($stmtPrefUpdate);
      }

      /* History layout */
      $stmtPrefUpdate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ? WHERE playerID = ? AND preference = 'historylayout'");
      if ($stmtPrefUpdate)
      {
        mysqli_stmt_bind_param($stmtPrefUpdate, "si", $prefHistoryLayout, $sessionPlayerId);
        mysqli_stmt_execute($stmtPrefUpdate);
        mysqli_stmt_close($stmtPrefUpdate);
      }

			/* Auto-Reload */
      $reloadValue = $CFG_MINAUTORELOAD;
      if (isset($_POST['txtReload']) && is_numeric($_POST['txtReload']) && intval($_POST['txtReload']) >= $CFG_MINAUTORELOAD)
        $reloadValue = intval($_POST['txtReload']);
      $stmtPrefUpdate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ? WHERE playerID = ? AND preference = 'autoreload'");
      if ($stmtPrefUpdate)
      {
        $reloadValueStr = (string)$reloadValue;
        mysqli_stmt_bind_param($stmtPrefUpdate, "si", $reloadValueStr, $sessionPlayerId);
        mysqli_stmt_execute($stmtPrefUpdate);
        mysqli_stmt_close($stmtPrefUpdate);
      }

			/* Email Notification */
			if ($CFG_USEEMAILNOTIFICATION)
			{
        $tmpEmailNotification = trim((string)($_POST['txtEmailNotification'] ?? ''));
        if ($tmpEmailNotification !== '' && !filter_var($tmpEmailNotification, FILTER_VALIDATE_EMAIL))
        {
          $_SESSION['flash_msg'] = webchessTranslate('Invalid email address. Keeping previous notification address.');
          $_SESSION['flash_type'] = 'warning';
        }
        else
        {
          $stmtPrefUpdate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['preferences'] . " SET value = ? WHERE playerID = ? AND preference = 'emailnotification'");
          if ($stmtPrefUpdate)
          {
            mysqli_stmt_bind_param($stmtPrefUpdate, "si", $tmpEmailNotification, $sessionPlayerId);
            mysqli_stmt_execute($stmtPrefUpdate);
            mysqli_stmt_close($stmtPrefUpdate);
          }
          $_SESSION['pref_emailnotification'] = $tmpEmailNotification;
        }
			}

			/* update current session */
      $_SESSION['pref_history'] = $prefHistory;
      $_SESSION['pref_historylayout'] = $prefHistoryLayout;
      $_SESSION['pref_theme'] =  $prefTheme;
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

      if (!isset($_SESSION['flash_type']))
      {
        if ($oldLanguage !== $tmpLanguage)
          $_SESSION['flash_msg'] = webchessTranslate('Preferences saved. Language updated.');
        else
          $_SESSION['flash_msg'] = webchessTranslate('Preferences saved.');
        $_SESSION['flash_type'] = 'success';
      }

      $redirectTo = 'mainmenu.php#preferences';

			break;

		case 'TestEmail':
      if ($CFG_USEEMAILNOTIFICATION)
      {
        $tmpMailTo = trim((string)($_POST['txtEmailNotification'] ?? ''));

        if ($tmpMailTo === '')
        {
          $_SESSION['flash_msg'] = webchessTranslate('Please enter an email address first.');
          $_SESSION['flash_type'] = 'warning';
        }
        elseif (!filter_var($tmpMailTo, FILTER_VALIDATE_EMAIL))
        {
          $_SESSION['flash_msg'] = webchessTranslate('Please enter a valid email address first.') . ' ' .
            webchessTranslate('Recipient:') . ' ' . $tmpMailTo;
          $_SESSION['flash_type'] = 'warning';
        }
        else
        {
          $_SESSION['pref_emailnotification'] = $tmpMailTo;
          if (webchessMail('test', $tmpMailTo, '', '', ''))
          {
            $_SESSION['flash_msg'] = webchessTranslate('Test email has been handed to the mail system.') . ' ' .
              webchessTranslate('Recipient:') . ' ' . $tmpMailTo . '. ' .
              webchessTranslate('Please check inbox/spam and server mail logs.');
            $_SESSION['flash_type'] = 'success';
          }
          else
          {
            $_SESSION['flash_msg'] = webchessTranslate('Sending test email failed in PHP mail().') . ' ' .
              webchessTranslate('Recipient:') . ' ' . $tmpMailTo . '. ' .
              webchessTranslate('Please check server mail configuration and logs.');
            $_SESSION['flash_type'] = 'danger';
          }
        }
        $redirectTo = 'mainmenu.php#preferences';
      }
			break;
                case 'HideMessage':
                        $stmtArchive = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['communication'] . " SET ack = 1 WHERE commID = ? AND toID = ?");
                        if ($stmtArchive)
                        {
                          $messageId = isset($_POST['messageID']) ? (int)$_POST['messageID'] : 0;
                          $sessionPlayerId = (int)$_SESSION['playerID'];
                          mysqli_stmt_bind_param($stmtArchive, "ii", $messageId, $sessionPlayerId);
                          mysqli_stmt_execute($stmtArchive);
                          mysqli_stmt_close($stmtArchive);
                        }
                        /* set a flash message to be shown after redirect */
                        $_SESSION['flash_msg'] = webchessTranslate('Message archived');
                        $_SESSION['flash_type'] = 'success';
                        break;

	}

  if ($redirectTo !== '')
  {
    header('Location: ' . $redirectTo);
    exit;
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
    <title>WebChess :: <?php echo webchessTranslate("Main Menu");?></title>
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
    function isValidEmailAddress(value)
    {
      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(value);
    }

    function updateTestEmailButtonState()
    {
      var emailInput = document.querySelector('input[name="txtEmailNotification"]');
      var testButton = document.getElementById('btnTestEmail');
      if (!emailInput || !testButton)
        return;

      testButton.disabled = !isValidEmailAddress(emailInput.value.trim());
    }

		function testEmail()
		{
      var emailInput = document.querySelector('input[name="txtEmailNotification"]');
      if (!emailInput || !isValidEmailAddress(emailInput.value.trim()))
      {
        alert("Please enter a valid email address first.");
        return;
      }
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

  window.addEventListener('DOMContentLoaded', function() {
    <?php if ($CFG_USEEMAILNOTIFICATION) { ?>
    var emailInput = document.querySelector('input[name="txtEmailNotification"]');
    if (emailInput)
      emailInput.addEventListener('input', updateTestEmailButtonState);
    updateTestEmailButtonState();
    <?php } ?>
  });

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
                <li class="nav-item"><a class="nav-link px-2" href="#continuegame"><?php echo webchessTranslate("Active games"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#invitations"><?php echo webchessTranslate("Pending challenges"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#messages"><?php echo webchessTranslate("Messages"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#challenge"><?php echo webchessTranslate("Challenge others"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#viewgame"><?php echo webchessTranslate("Replay"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#preferences"><?php echo webchessTranslate("Preferences"); ?></a></li>
                <li class="nav-item"><a class="nav-link px-2" href="#personalinfo"><?php echo webchessTranslate("Personal"); ?></a></li>
                <li class="nav-item"><button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" type="button" onclick="toggleTheme()" data-title-dark="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>" data-title-light="<?php echo htmlspecialchars(webchessTranslate('Switch to Light Mode'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>">&#9790;</button></li>
                <li class="nav-item"><button class="btn btn-outline-danger btn-sm" type="button" onclick="reload()" title="<?php echo webchessTranslate('Reload'); ?>"><?php echo webchessTranslate("Reload"); ?></button></li>
                <li class="nav-item"><button class="btn btn-danger btn-sm" type="button" onclick="logout()"><?php echo webchessTranslate("Logout"); ?></button></li>
            </ul>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['flash_msg']) && $_SESSION['flash_msg'] !== ''): ?>
<div class="container mb-3">
    <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_SESSION['flash_msg']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
endif;
?>

<form name="logOutForm" action="mainmenu.php" method="post">
    <input type="hidden" name="ToDo" value="Logout" />
    <?php echo webchessCsrfField(); ?>
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
                    <div class="card-header bg-primary text-white"><h5 class="mb-0"><?php echo webchessTranslate("Games in Progress");?></h5></div>
                    <div class="card-body">
                        <form name="existingGames" action="chess.php" method="post">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?php echo webchessTranslate("Id");?></th>
                                            <th><?php echo webchessTranslate("White");?></th>
                                            <th><?php echo webchessTranslate("Black");?></th>
                                            <th><?php echo webchessTranslate("Mvs");?></th>
                                            <th><?php echo webchessTranslate("Current Turn");?></th>
                                            <th><?php echo webchessTranslate("Last Move");?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="inProgrTblBdy">
                                        <?php
                                        $stmtGames = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['games'] . " WHERE gameMessage IS NULL AND (whitePlayer = ? OR blackPlayer = ?) ORDER BY dateCreated");
                                        $tmpGames = false;
                                        if ($stmtGames) {
                                            $playerId = (int)$_SESSION['playerID'];
                                            mysqli_stmt_bind_param($stmtGames, "ii", $playerId, $playerId);
                                            mysqli_stmt_execute($stmtGames);
                                            $tmpGames = mysqli_stmt_get_result($stmtGames);
                                            mysqli_stmt_close($stmtGames);
                                        }
                                        if (!$tmpGames || mysqli_num_rows($tmpGames) == 0): ?>
                                            <tr><td colspan="6" class="text-center text-muted"><?php echo webchessTranslate("You do not currently have any games in progress"); ?></td></tr>
                                        <?php else:
                                            while($tmpGame = mysqli_fetch_assoc($tmpGames)):
                                                $whiteNick = webchessGetNickByPlayerId($dbh, $CFG_TABLE['players'], (int)$tmpGame['whitePlayer']);
                                                $blackNick = webchessGetNickByPlayerId($dbh, $CFG_TABLE['players'], (int)$tmpGame['blackPlayer']);
                                                $numMoves = webchessCountMovesByGameId($dbh, $CFG_TABLE['history'], (int)$tmpGame['gameID']);
                                                $isWhite = ($tmpGame['whitePlayer'] == $_SESSION['playerID']);
                                                $isMyTurn = (($numMoves % 2 == 0) == $isWhite);
                                        ?>
                                            <tr>
                                                                          <td><a href="javascript:loadGame(<?php echo (int)$tmpGame['gameID']; ?>)" class="btn btn-sm btn-outline-primary">#<?php echo (int)$tmpGame['gameID']; ?></a></td>
                                                                          <td><?php echo htmlspecialchars((string)$whiteNick, ENT_QUOTES, 'UTF-8'); ?></td>
                                                                          <td><?php echo htmlspecialchars((string)$blackNick, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo floor($numMoves / 2); ?></td>
                                                <td><span class="badge <?php echo $isMyTurn ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $isMyTurn ? webchessTranslate("Your move") : webchessTranslate("Opponent"); ?></span></td>
                                                <td><small><?php echo substr($tmpGame['lastMove'], 0, -3); ?></small></td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 p-3 bg-light rounded border">
                                <label class="form-label d-block fw-bold"><?php echo webchessTranslate("Will both players play from the same computer?");?></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" name="rdoShare" type="radio" value="" id="shareYes" />
                                    <label class="form-check-label" for="shareYes"><?php echo webchessTranslate("Yes");?></label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" name="rdoShare" type="radio" value="no" id="shareNo" checked="checked" />
                                    <label class="form-check-label" for="shareNo"><?php echo webchessTranslate("No");?></label>
                                </div>
                            </div>
                            <input type="hidden" name="gameID" value="" />
                            <input type="hidden" name="sharePC" value="no" />
                            <?php echo webchessCsrfField(); ?>
                        </form>
                        <div class="mt-3 alert alert-warning small">
                            <strong><?php echo webchessTranslate("WARNING!");?></strong> <?php echo webchessTranslate("Games will expire WITHOUT NOTICE if a move isn't made after") . " " . ($CFG_EXPIREGAME) . " " . webchessTranslate("days!");?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invitations -->
            <div id="invitations" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white"><h5 class="mb-0"><?php echo webchessTranslate("Challenges");?></h5></div>
                    <div class="card-body">
                        <form name="responseToInvite" action="mainmenu.php" method="post">
                            <h6 class="fw-bold"><?php echo webchessTranslate("Challenges from other players");?></h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm">
                                    <thead class="table-light"><tr><th>ID</th><th>White</th><th>Black</th><th>Issued</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $stmtInvites = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['games'] . " WHERE gameMessage = 'playerInvited' AND ((whitePlayer = ? AND messageFrom = 'black') OR (blackPlayer = ? AND messageFrom = 'white')) ORDER BY dateCreated");
                                        $tmpGames = false;
                                        if ($stmtInvites) {
                                            $playerId = (int)$_SESSION['playerID'];
                                            mysqli_stmt_bind_param($stmtInvites, "ii", $playerId, $playerId);
                                            mysqli_stmt_execute($stmtInvites);
                                            $tmpGames = mysqli_stmt_get_result($stmtInvites);
                                            mysqli_stmt_close($stmtInvites);
                                        }
                                        if (!$tmpGames || mysqli_num_rows($tmpGames) == 0): ?>
                                            <tr><td colspan="5" class="text-center text-muted"><?php echo webchessTranslate("You are not currently invited to any games"); ?></td></tr>
                                        <?php else:
                                            while($tmpGame = mysqli_fetch_assoc($tmpGames)):
                                                $tmpFrom = ($tmpGame['whitePlayer'] == $_SESSION['playerID']) ? 'white' : 'black';
                                                $whiteNick = webchessGetNickByPlayerId($dbh, $CFG_TABLE['players'], (int)$tmpGame['whitePlayer']);
                                                $blackNick = webchessGetNickByPlayerId($dbh, $CFG_TABLE['players'], (int)$tmpGame['blackPlayer']);
                                        ?>
                                            <tr>
                                                                          <td><?php echo (int)$tmpGame['gameID']; ?></td>
                                                                          <td><?php echo htmlspecialchars($whiteNick, ENT_QUOTES, 'UTF-8'); ?></td>
                                                                          <td><?php echo htmlspecialchars($blackNick, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><small><?php echo substr($tmpGame['dateCreated'], 0, -3); ?></small></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                                                        <button class="btn btn-success" type="button" onclick="sendResponse('accepted', '<?php echo $tmpFrom; ?>', <?php echo $tmpGame['gameID']; ?>)"><?php echo webchessTranslate("Accept"); ?></button>
                                                                                        <button class="btn btn-outline-danger" type="button" onclick="sendResponse('declined', '<?php echo $tmpFrom; ?>', <?php echo $tmpGame['gameID']; ?>)"><?php echo webchessTranslate("Decline"); ?></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <input type="hidden" name="response" value="" /><input type="hidden" name="messageFrom" value="" /><input type="hidden" name="gameID" value="" /><input type="hidden" name="ToDo" value="ResponseToInvite" />
                            <?php echo webchessCsrfField(); ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Personal Info -->
            <div id="personalinfo" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white"><h5 class="mb-0"><?php echo webchessTranslate("Personal information");?></h5></div>
                    <div class="card-body">
                        <form name="PersonalInfo" action="mainmenu.php" method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo webchessTranslate("First Name"); ?></label>
                                          <input name="txtFirstName" type="text" class="form-control" value="<?php echo htmlspecialchars((string)($_SESSION['firstName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo webchessTranslate("Last Name"); ?></label>
                                          <input name="txtLastName" type="text" class="form-control" value="<?php echo htmlspecialchars((string)($_SESSION['lastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <?php if ($CFG_NICKCHANGEALLOWED): ?>
                            <div class="col-12">
                                <label class="form-label">Nick</label>
                                          <input name="txtNick" type="text" class="form-control" value="<?php echo htmlspecialchars((string)($_SESSION['nick'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <?php endif; ?>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo webchessTranslate("Current Password"); ?></label>
                                <input name="pwdOldPassword" type="password" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo webchessTranslate("New Password"); ?></label>
                                <input name="pwdPassword" type="password" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?php echo webchessTranslate("Password Confirmation"); ?></label>
                                <input name="pwdPassword2" type="password" class="form-control" />
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary btn-lg" onclick="validatePersonalInfo()"><i class="bi bi-check-circle"></i> <?php echo webchessTranslate("Update");?></button>
                                <input type="hidden" name="ToDo" value="UpdatePersonalInfo" />
                                <?php echo webchessCsrfField(); ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preferences -->
            <div id="preferences" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white"><h5 class="mb-0"><?php echo webchessTranslate("Preferences");?></h5></div>
                    <div class="card-body">
                        <form name="userdata" method="post" action="mainmenu.php" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold"><?php echo webchessTranslate("History Format");?></label>
                                <div class="form-check"><input class="form-check-input" name="rdoHistory" type="radio" value="pgn" <?php if ($_SESSION['pref_history'] == 'pgn') echo 'checked'; ?> /> PGN</div>
                                <div class="form-check"><input class="form-check-input" name="rdoHistory" type="radio" value="verbous" <?php if ($_SESSION['pref_history'] != 'pgn') echo 'checked'; ?> /> Verbose</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold"><?php echo webchessTranslate("History Layout");?></label>
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
                            <?php if ($CFG_USEEMAILNOTIFICATION): ?>
                            <div class="col-12">
                                <label class="form-label fw-bold"><?php echo webchessTranslate("Email notification address");?></label>
                                <div class="input-group">
                                    <input type="email" class="form-control" name="txtEmailNotification"
                                           value="<?php echo htmlspecialchars($_SESSION['pref_emailnotification'] ?? ''); ?>"
                                           placeholder="<?php echo webchessTranslate("Enter email address for move notifications"); ?>" />
                                                  <button id="btnTestEmail" type="button" class="btn btn-outline-secondary" onclick="testEmail()" title="<?php echo webchessTranslate("Send a test email to the address above"); ?>" disabled>
                                                    <?php echo webchessTranslate("Test");?>
                                                  </button>
                                </div>
                                <div class="form-text"><?php echo webchessTranslate("Leave empty to disable email notifications.");?></div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <button type="submit" class="btn btn-secondary btn-lg"><i class="bi bi-sliders"></i> <?php echo webchessTranslate("Update");?></button>
                                <input type="hidden" name="ToDo" value="UpdatePrefs" />
                                <?php echo webchessCsrfField(); ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Other sections (Messages, Challenge, Replay) -->
            <div id="messages" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark"><h5 class="mb-0"><?php echo webchessTranslate("Messages");?></h5></div>
                    <div class="card-body">
                         <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo webchessTranslate("Send message to player");?></label>
                            <div class="input-group">
                                <select id="player_select" class="form-select">
                                    <?php
                                    $stmtPlayers = mysqli_prepare($dbh, "SELECT playerID, nick FROM " . $CFG_TABLE['players'] . " WHERE playerID <> ?");
                                    $tmpPlayers = false;
                                    if ($stmtPlayers) {
                                        $playerId = (int)$_SESSION['playerID'];
                                        mysqli_stmt_bind_param($stmtPlayers, "i", $playerId);
                                        mysqli_stmt_execute($stmtPlayers);
                                        $tmpPlayers = mysqli_stmt_get_result($stmtPlayers);
                                        mysqli_stmt_close($stmtPlayers);
                                    }
                                    while($tmpPlayer = mysqli_fetch_assoc($tmpPlayers)) {
                                                        echo ('<option value="'.(int)$tmpPlayer['playerID'].'"> '.htmlspecialchars((string)$tmpPlayer['nick'], ENT_QUOTES, 'UTF-8')."</option>\n");
                                    }
                                    ?>
                                </select>
                                <button class="btn btn-primary" type="button" onclick="MessagePlayer(document.getElementById('player_select').value)"><i class="bi bi-chat-dots"></i> <?php echo webchessTranslate("Open Window");?></button>
                            </div>
                        </div>

                        <h6 class="fw-bold"><?php echo webchessTranslate("Current messages");?></h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr><th>Player</th><th>Subject</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $SqlQuery = "SELECT * FROM " . $CFG_TABLE['communication'] . " left join " . $CFG_TABLE['players'] . " on " . $CFG_TABLE['communication'] . ".fromID=" . $CFG_TABLE['players'] . ".playerID WHERE ((toID is null) or (toID=?)) and ((fromID is null) or (fromID=playerID)) and ack=0 and gameID is null order by " . $CFG_TABLE['communication'] . ".postDate desc";
                                    $stmtComm = mysqli_prepare($dbh, $SqlQuery);
                                    $tmpGames = false;
                                    if ($stmtComm) {
                                        $playerId = (int)$_SESSION['playerID'];
                                        mysqli_stmt_bind_param($stmtComm, "i", $playerId);
                                        mysqli_stmt_execute($stmtComm);
                                        $tmpGames = mysqli_stmt_get_result($stmtComm);
                                        mysqli_stmt_close($stmtComm);
                                    }
                                    if (!$tmpGames || mysqli_num_rows($tmpGames) == 0): ?>
                                        <tr><td colspan="3" class="text-center text-muted"><?php echo webchessTranslate("No pending messages"); ?></td></tr>
                                    <?php else:
                                        while($tmpGame = mysqli_fetch_assoc($tmpGames)): ?>
                                        <tr>
                                                                  <td><a href="javascript:viewMessage(<?php echo (int)$tmpGame['commID']; ?>)"><?php echo htmlspecialchars((string)($tmpGame['fromID']!=0?$tmpGame['nick']:"Webchess"), ENT_QUOTES, 'UTF-8'); ?></a></td>
                                                                  <td><?php echo htmlspecialchars((string)(strlen($tmpGame['title'])>40? substr($tmpGame['title'],0,37)."..." : $tmpGame['title']), ENT_QUOTES, 'UTF-8'); ?></td>
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
                    <div class="card-header bg-dark text-white"><h5 class="mb-0"><?php echo webchessTranslate("Issue a challenge");?></h5></div>
                    <div class="card-body">
                        <form name="newchallenge" action="mainmenu.php" method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php echo webchessTranslate("Select Opponent");?></label>
                                <select name="opponent" class="form-select">
                                    <?php
                                    $stmtPlayers = mysqli_prepare($dbh, "SELECT playerID, nick FROM " . $CFG_TABLE['players'] . " WHERE playerID <> ?");
                                    $tmpPlayers = false;
                                    if ($stmtPlayers) {
                                        $playerId = (int)$_SESSION['playerID'];
                                        mysqli_stmt_bind_param($stmtPlayers, "i", $playerId);
                                        mysqli_stmt_execute($stmtPlayers);
                                        $tmpPlayers = mysqli_stmt_get_result($stmtPlayers);
                                        mysqli_stmt_close($stmtPlayers);
                                    }
                                    while($tmpPlayer = mysqli_fetch_assoc($tmpPlayers)) {
                                                        echo ('<option value="'.(int)$tmpPlayer['playerID'].'"> '.htmlspecialchars((string)$tmpPlayer['nick'], ENT_QUOTES, 'UTF-8')."</option>\n");
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo webchessTranslate("Your Color");?></label>
                                <div class="mt-2">
                                    <div class="form-check form-check-inline"><input class="form-check-input" name="color" type="radio" value="random" checked /> Random</div>
                                    <div class="form-check form-check-inline"><input class="form-check-input" name="color" type="radio" value="white" /> White</div>
                                    <div class="form-check form-check-inline"><input class="form-check-input" name="color" type="radio" value="black" /> Black</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-suit-heart"></i> <?php echo webchessTranslate("Invite");?></button>
                                <input type="hidden" name="ToDo" value="InvitePlayer" />
                                <?php echo webchessCsrfField(); ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="viewgame" class="section-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h5 class="mb-0"><?php echo webchessTranslate("View finished games");?></h5></div>
                    <div class="card-body">
                         <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr><th>ID</th><th>White</th><th>Black</th><th>Mvs</th><th>Result</th><th>Last Move</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $tmpGames = false;
                                    $stmtEndedGames = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['games'] . " WHERE (gameMessage <> '' AND gameMessage <> 'playerInvited' AND gameMessage <> 'inviteDeclined') AND (whitePlayer = ? OR blackPlayer = ?) ORDER BY lastMove DESC");
                                    if ($stmtEndedGames) {
                                        $playerId = (int)$_SESSION['playerID'];
                                        mysqli_stmt_bind_param($stmtEndedGames, "ii", $playerId, $playerId);
                                        mysqli_stmt_execute($stmtEndedGames);
                                        $tmpGames = mysqli_stmt_get_result($stmtEndedGames);
                                        mysqli_stmt_close($stmtEndedGames);
                                    }
                                    if (!$tmpGames || mysqli_num_rows($tmpGames) == 0): ?>
                                        <tr><td colspan="6" class="text-center text-muted"><?php echo webchessTranslate("No finished games found"); ?></td></tr>
                                    <?php else:
                                        while($tmpGame = mysqli_fetch_assoc($tmpGames)):
                                            $tmpNumMoves = webchessCountMovesByGameId($dbh, $CFG_TABLE['history'], (int)$tmpGame['gameID']);
                                            $whiteNick = webchessGetNickByPlayerId($dbh, $CFG_TABLE['players'], (int)$tmpGame['whitePlayer']);
                                            $blackNick = webchessGetNickByPlayerId($dbh, $CFG_TABLE['players'], (int)$tmpGame['blackPlayer']);
                                    ?>
                                        <tr>
                                                                  <td><a href="javascript:loadGame(<?php echo (int)$tmpGame['gameID']; ?>)" class="btn btn-xs btn-outline-success">#<?php echo (int)$tmpGame['gameID']; ?></a></td>
                                                                  <td><?php echo htmlspecialchars($whiteNick, ENT_QUOTES, 'UTF-8'); ?></td>
                                                                  <td><?php echo htmlspecialchars($blackNick, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo floor($tmpNumMoves / 2); ?></td>
                                                                  <td><small><?php echo htmlspecialchars((string)$tmpGame['gameMessage'], ENT_QUOTES, 'UTF-8'); ?></small></td>
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
<form name="messageViewForm" method="post" action="viewmessage.php"><input type="hidden" name="messageID" value="" /><?php echo webchessCsrfField(); ?></form>
<form name="endedGames" action="chess.php" method="post"><input type="hidden" name="gameID" value="" /><input type="hidden" name="sharePC" value="no" /><?php echo webchessCsrfField(); ?></form>
<form name="withdrawRequestForm" action="mainmenu.php" method="post"><input type="hidden" name="gameID" value="" /><input type="hidden" name="ToDo" value="WithdrawRequest" /><?php echo webchessCsrfField(); ?></form>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php mysqli_close($dbh); ?>

