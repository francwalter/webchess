<?php
// $Id: opponentspassword.php,v 1.6 2010/08/15 09:56:12 sandking Exp $

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

	if (!isset($_CHESSUTILS))
		require 'chessutils.php';

	require 'lang.php';
	require 'csrf.php';

	fixOldPHPVersions();

	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !webchessCsrfValidateRequest())
	{
		$_SESSION['flash_msg'] = webchessTranslate('Your session form token expired. Please reload the page and try again.');
		$_SESSION['flash_type'] = 'danger';
		header('Location: mainmenu.php');
		exit();
	}

	/* check session status */
	require 'sessioncheck.php';

	/* connect to database */
	require 'connectdb.php';

	/* invalid password flag */
	$isInvalidPassword = false;

	function webchessFetchGamePlayers($dbh, $gamesTable, $gameID)
	{
		$stmt = mysqli_prepare($dbh, "SELECT whitePlayer, blackPlayer FROM " . $gamesTable . " WHERE gameID = ? LIMIT 1");
		if (!$stmt)
			return null;
		$gameID = (int)$gameID;
		mysqli_stmt_bind_param($stmt, "i", $gameID);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$row = $res ? mysqli_fetch_assoc($res) : null;
		mysqli_stmt_close($stmt);
		return $row ?: null;
	}

	/* check if submitting opponents login information */
	if (isset($_POST['opponentsID']))
	{
		$opponentsID = (int)$_POST['opponentsID'];
		$opponentsNick = $_POST['opponentsNick'];
		$gameID = isset($_POST['gameID']) ? (int)$_POST['gameID'] : (isset($_SESSION['gameID']) ? (int)$_SESSION['gameID'] : 0);
		if ($gameID <= 0 || !webchessPlayerOwnsGame($dbh, $gameID, (int)$_SESSION['playerID']))
		{
			header('Location: mainmenu.php');
			exit();
		}

		$tmpPlayers = webchessFetchGamePlayers($dbh, $CFG_TABLE['games'], $gameID);
		if (!$tmpPlayers)
		{
			header('Location: mainmenu.php');
			exit();
		}

		$expectedOpponentID = ((int)$tmpPlayers['whitePlayer'] === (int)$_SESSION['playerID']) ? (int)$tmpPlayers['blackPlayer'] : (int)$tmpPlayers['whitePlayer'];
		if ($opponentsID !== $expectedOpponentID)
		{
			header('Location: mainmenu.php');
			exit();
		}

		/* get opponents password from DB */
		$dbPassword = null;
		$stmtOpponentPwd = mysqli_prepare($dbh, "SELECT password FROM " . $CFG_TABLE['players'] . " WHERE playerID = ?");
		if ($stmtOpponentPwd)
		{
			mysqli_stmt_bind_param($stmtOpponentPwd, "i", $opponentsID);
			mysqli_stmt_execute($stmtOpponentPwd);
			$tmpPassword = mysqli_stmt_get_result($stmtOpponentPwd);
			$tmpRow = $tmpPassword ? mysqli_fetch_row($tmpPassword) : null;
			$dbPassword = $tmpRow ? $tmpRow[0] : null;
			mysqli_stmt_close($stmtOpponentPwd);
		}

		/* check to see if supplied password matched that of the DB */
		if (webchessPasswordMatches((string)$_POST['pwdPassword'], (string)$dbPassword))
		{
			if (webchessPasswordNeedsRehash((string)$dbPassword))
			{
				$newOpponentHash = password_hash((string)$_POST['pwdPassword'], PASSWORD_DEFAULT);
				$stmtOpponentRehash = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['players'] . " SET password = ? WHERE playerID = ?");
				if ($stmtOpponentRehash)
				{
					mysqli_stmt_bind_param($stmtOpponentRehash, "si", $newOpponentHash, $opponentsID);
					mysqli_stmt_execute($stmtOpponentRehash);
					mysqli_stmt_close($stmtOpponentRehash);
				}
			}

			$_SESSION['isSharedPC'] = true;
            error_log("opponentspassword.php: Shared PC mode enabled. Loading chess.php for gameID: " . (isset($_POST['gameID']) ? $_POST['gameID'] : 'NOT SET'));
									if ($gameID > 0) {
										$_SESSION['gameID'] = $gameID; // Ensure gameID is set in session
            }
			require 'chess.php';
			die();
		}
		/* else password is invalid */
		else {
			$isInvalidPassword = true;
            error_log("opponentspassword.php: Invalid password for opponentID: " . $opponentsID);
        }

	}
	/* else user is arriving here for the first time */
	else
	{
        error_log("opponentspassword.php: First time access. POST gameID: " . (isset($_POST['gameID']) ? $_POST['gameID'] : 'NOT SET'));
        if (!isset($_POST['gameID'])) {
            // This should not happen if redirected from mainmenu.php correctly
            error_log("opponentspassword.php: No gameID in POST. Redirecting to mainmenu.php");
            header('Location: mainmenu.php');
            exit();
        }

		$gameID = (int)$_POST['gameID'];
		if ($gameID <= 0 || !webchessPlayerOwnsGame($dbh, $gameID, (int)$_SESSION['playerID']))
		{
			header('Location: mainmenu.php');
			exit();
		}

		/* get the players associated with this game */
		$tmpPlayers = webchessFetchGamePlayers($dbh, $CFG_TABLE['games'], $gameID);
		if (!$tmpPlayers)
		{
			header('Location: mainmenu.php');
			exit();
		}

        error_log("opponentspassword.php: GameID: " . $_POST['gameID'] . ", WhitePlayer: " . $tmpPlayers['whitePlayer'] . ", BlackPlayer: " . $tmpPlayers['blackPlayer'] . ", SESSION playerID: " . $_SESSION['playerID']);

		/* determine which one is the opponent of the player logged in */
		if ($tmpPlayers['whitePlayer'] == $_SESSION['playerID'])
			$opponentsID = $tmpPlayers['blackPlayer'];
		else
			$opponentsID = $tmpPlayers['whitePlayer'];

        error_log("opponentspassword.php: Opponent ID: " . $opponentsID);

		/* get the opponents information */
		$opponentsNick = '';
		$stmtNick = mysqli_prepare($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ? LIMIT 1");
		if ($stmtNick)
		{
			mysqli_stmt_bind_param($stmtNick, "i", $opponentsID);
			mysqli_stmt_execute($stmtNick);
			$tmpNick = mysqli_stmt_get_result($stmtNick);
			$tmpNickRow = $tmpNick ? mysqli_fetch_row($tmpNick) : null;
			$opponentsNick = $tmpNickRow ? (string)$tmpNickRow[0] : '';
			mysqli_stmt_close($stmtNick);
		}
        error_log("opponentspassword.php: Opponent Nick: " . $opponentsNick);
	}

	mysqli_close($dbh);
?>

<!doctype html>
<html lang="<?php echo htmlspecialchars(getGuiLanguage(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WebChess :: <?php echo htmlspecialchars(webchessTranslate('Login'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/theme.css">
    <script src="javascript/theme.js"></script>
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width: 520px;">
    <div class="text-end mb-3">
        <button id="theme-toggle-btn" class="btn btn-outline-secondary" type="button"
                onclick="toggleTheme()"
                data-title-dark="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>"
                data-title-light="<?php echo htmlspecialchars(webchessTranslate('Switch to Light Mode'), ENT_QUOTES, 'UTF-8'); ?>"
                title="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>">&#9790;</button>
    </div>

    <div class="card shadow">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">WebChess :: <?php echo htmlspecialchars(webchessTranslate('Login'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars(webchessTranslate('Enter password for ') . $opponentsNick, ENT_QUOTES, 'UTF-8'); ?></p>

            <?php if ($isInvalidPassword): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars(webchessTranslate('Invalid Nick or Password. Please try again'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form name="loginForm" id="loginForm" method="post" action="opponentspassword.php">
                <div class="mb-3">
                    <label class="form-label" for="pwdPassword"><?php echo htmlspecialchars(webchessTranslate('Password'), ENT_QUOTES, 'UTF-8'); ?></label>
                    <input id="pwdPassword" name="pwdPassword" type="password"
                           class="form-control" autocomplete="current-password" autofocus required>
                </div>
                <input name="opponentsNick" type="hidden" value="<?php echo htmlspecialchars((string)$opponentsNick, ENT_QUOTES, 'UTF-8'); ?>">
                <input name="opponentsID" type="hidden" value="<?php echo (int)$opponentsID; ?>">
                <input name="gameID" type="hidden" value="<?php echo (int)$gameID; ?>">
                <?php echo webchessCsrfField(); ?>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(webchessTranslate('Login'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <a class="btn btn-outline-secondary" href="mainmenu.php"><?php echo htmlspecialchars(webchessTranslate('Cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

