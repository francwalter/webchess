<?php

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

require 'csrf.php';
require 'chessutils.php';

	/* check session status */
	require 'sessioncheck.php';
?>
<!DOCTYPE html>
<html>

	<head>
		<meta charset="ISO-8859-1">
		<title>Send Message</title>

<?php
	$lisega_light_blue="#0099FF";
	$lisega_medium_blue="#0000FD";
	$lisega_dark_blue="#18189C";
	$lisega_white="#BBC9FB";
	$real_white="#CCCCFF";


	$bg = $lisega_dark_blue;
	$menu = $lisega_dark_blue; 
	$blocks = $lisega_light_blue;
	$top = $lisega_medium_blue;
	$box = $lisega_medium_blue;
	$content = $real_white;

if (!isset($_CONFIG))
                require 'config.php';

require "connectdb.php";
$id=$_SESSION['playerID'];

if(isset($_POST['newMessage']))
{
	if (!webchessCsrfValidateRequest())
		die('Invalid form token. Please reload and try again.');

	/* echo("<PRE>");
	print_r($_POST);
	echo("</PRE>"); */
        $fromPerson = (int)$id;
        $toPerson = (isset($_POST['to']) && $_POST['to'] != '') ? (int)$_POST['to'] : 0;

	/* echo("From $fromPerson, To $toPerson<br>"); */

		if ($toPerson > 0) {
		$mGame = (isset($_POST['forGame']) && $_POST['forGame'] != '') ? (int)$_POST['forGame'] : null;
		if ($mGame !== null && !webchessPlayerOwnsGame($dbh, $mGame, $fromPerson))
			die('Unauthorized game selection.');

		$msgtitle = trim((string)($_POST['txtTitle'] ?? ''));
		$msgtext = trim((string)($_POST['txtMessage'] ?? ''));
		$msgtype = "0"; // Always 0... yet..

		if ($mGame === null) {
			$stmt = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['communication'] . " (gameID,fromID,toID,title,text,postDate,expireDate,ack,commType) VALUES (NULL, ?, ?, ?, ?, NOW(), NULL, 0, ?)");
			if (!$stmt)
				die('Database error.');
			mysqli_stmt_bind_param($stmt, "iisss", $fromPerson, $toPerson, $msgtitle, $msgtext, $msgtype);
		} else {
			$stmt = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['communication'] . " (gameID,fromID,toID,title,text,postDate,expireDate,ack,commType) VALUES (?, ?, ?, ?, ?, NOW(), NULL, 0, ?)");
			if (!$stmt)
				die('Database error.');
			mysqli_stmt_bind_param($stmt, "iiisss", $mGame, $fromPerson, $toPerson, $msgtitle, $msgtext, $msgtype);
		}

		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
?>
Message Sent!
<script type="text/javascript">
window.close()
</script>
<?php
die();
}
}

?>

	</head>

	<body style="background-color: #808080;">
		<div style="text-align: center;">
			<form action="sendmessage.php" method="post" name="FormName">
				<?php echo webchessCsrfField(); ?>
				Message Recipient:<br>
				<input type="hidden" name="from" value="<?php echo (int)$id; ?>">
				<select name="to" size="1">
<?php
					$to_get = isset($_GET['to']) ? $_GET['to'] : '';
					$stmtPlayers = mysqli_prepare($dbh, "SELECT playerID, nick FROM " . $CFG_TABLE['players'] . " WHERE playerID <> ? ORDER BY nick ASC");
	                                if (!$stmtPlayers)
	                                    die('Database error.');
	                                $idInt = (int)$id;
	                                mysqli_stmt_bind_param($stmtPlayers, "i", $idInt);
	                                mysqli_stmt_execute($stmtPlayers);
	                                $tmpPlayers = mysqli_stmt_get_result($stmtPlayers);
	                                        while($tmpPlayers && ($tmpPlayer = mysqli_fetch_assoc($tmpPlayers)))
                                        {
                                                if ($tmpPlayer['nick']){
						if($tmpPlayer['playerID'] == $to_get)
	        	                                        echo("<option value='".(int)$tmpPlayer['playerID']."' selected=\"selected\"> ".htmlspecialchars($tmpPlayer['nick'], ENT_QUOTES, 'UTF-8')."</option>\n");
						else
	                	                                echo("<option value='".(int)$tmpPlayer['playerID']."'> ".htmlspecialchars($tmpPlayer['nick'], ENT_QUOTES, 'UTF-8')."</option>\n");
						}
                                        }
	                                mysqli_stmt_close($stmtPlayers);

?>				
				</select><br>
				<br>
				Message Subject:<br>
				<input type="text" name="txtTitle" size="54" style="border: 0;"><br>
				<br>
				Message Text:<br>
				<textarea name="txtMessage" rows="10" cols="52" tabindex="1"></textarea><br><br>
				<input type="submit" name="newMessage" value="Send Message" style="border: 0;"> 
				<input type="button" name="btnCancel" value="Cancel" style="border: 0;" onClick="window.close();"><br>
			</form>
		</div>
	</body>

</html>
