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

	/* check if submitting opponents login information */
	if (isset($_POST['opponentsID']))
	{
		$opponentsID = (int)$_POST['opponentsID'];
		$opponentsNick = $_POST['opponentsNick'];

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
            if (isset($_POST['gameID'])) {
                $_SESSION['gameID'] = $_POST['gameID']; // Ensure gameID is set in session
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

		/* get the players associated with this game */
		$tmpQuery = "SELECT whitePlayer, blackPlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ".(int)$_POST['gameID'];
		$tmpGameData = mysqli_query($dbh, $tmpQuery);
		$tmpPlayers = mysqli_fetch_assoc($tmpGameData);

        error_log("opponentspassword.php: GameID: " . $_POST['gameID'] . ", WhitePlayer: " . $tmpPlayers['whitePlayer'] . ", BlackPlayer: " . $tmpPlayers['blackPlayer'] . ", SESSION playerID: " . $_SESSION['playerID']);

		/* determine which one is the opponent of the player logged in */
		if ($tmpPlayers['whitePlayer'] == $_SESSION['playerID'])
			$opponentsID = $tmpPlayers['blackPlayer'];
		else
			$opponentsID = $tmpPlayers['whitePlayer'];

        error_log("opponentspassword.php: Opponent ID: " . $opponentsID);

		/* get the opponents information */
		$tmpQuery = "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ".(int)$opponentsID;
		$tmpNick = mysqli_query($dbh, $tmpQuery);
		$opponentsNick = mysqli_fetch_row($tmpNick)[0];
        error_log("opponentspassword.php: Opponent Nick: " . $opponentsNick);
	}

	mysqli_close($dbh);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="styles/userlogin.css" type="text/css" />
<title>WebChess :: <?php echo webchessTranslate("Login");?></title>
<script language="javascript" type="text/javascript">
window.onload = function()
{
<?php
	if ($isInvalidPassword)
		echo "alert('Invalid password. Please try again');\n";
?>
	document.loginForm.opponentsNick.focus();
	document.loginForm.opponentsNick.select();
}
</script>
</head>

<body>

<div id="header">
  <div id="heading">WebChess :: <?php echo webchessTranslate("Login");?></div>
</div>

<div id="ctr" align="center">
	<div class="login">
		<div class="login-form">
			<form name="loginForm" id="loginForm" method="post" action="opponentspassword.php">
				<div class="form-block">
                                        <div class="inputlabel"><?php echo webchessTranslate("Password");?></div>
					<div><input id="pwdPassword" name="pwdPassword" type="password" class="inputbox" size="15" /></div>
					<input name="opponentsNick" type="hidden" value="<?php echo htmlspecialchars((string)($opponentsNick ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
					<input name="opponentsID" type="hidden" value="<?php echo (int)($opponentsID ?? 0); ?>" />
					<input name="gameID" value="<?php echo (int)($_POST['gameID'] ?? 0); ?>" type="hidden" />
					<?php echo webchessCsrfField(); ?>
					<div align="left">
						<input type="submit" name="login" class="button" value="<?php echo webchessTranslate("Login");?>" />
						<input name="Cancel" class="button" value="<?php echo webchessTranslate("Cancel");?>" type="button" onClick="window.open('mainmenu.php', '_self')" /></div>
				</div>
			</form>
		</div>
		<div class="login-text">
			<div class="ctr"><img src="images/webchess.jpg" width="65" height="92" alt="security" /></div>
						<p><?php echo webchessTranslate("Enter password for ");?><?php echo htmlspecialchars((string)($opponentsNick ?? 'opponent'), ENT_QUOTES, 'UTF-8'); ?></p>
    	</div>
		<div class="clr"></div>
	</div>
</div>

<div id="break"></div>
<noscript>
!Warning! Javascript must be enabled for proper operation of WebChess
</noscript>
<div class="footer" align="center">
<div align="center"><?php echo "WebChess " . webchessTranslate("Version") . " 1.0.0, " . webchessTranslate("last updated") ." ". webchessTranslate("August"). " 15, 2010"?></div>
<div align="center"><a href="http://webchess.sourceforge.net/"><?php echo webchessTranslate("WebChess");?></a> <?php echo webchessTranslate("is Free Software released under the GNU General Public License (GPL).");?></div>
</div>
</body>
</html>

