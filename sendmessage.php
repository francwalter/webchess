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

session_start();

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
	/* echo("<PRE>");
	print_r($_POST);
	echo("</PRE>"); */
        $fromPerson = (isset($_POST['from']) && $_POST['from'] != '') ? (int)$_POST['from'] : "NULL";
        $toPerson = (isset($_POST['to']) && $_POST['to'] != '') ? (int)$_POST['to'] : "NULL";

	/* echo("From $fromPerson, To $toPerson<br>"); */

        if ( ($fromPerson !== "NULL") && ($toPerson !== "NULL") ) {
        $mGame = (isset($_POST['forGame']) && $_POST['forGame'] != '') ? (int)$_POST['forGame'] : "NULL";
        $msgtitle = mysqli_real_escape_string($dbh, $_POST['txtTitle']);
        $msgtext = mysqli_real_escape_string($dbh, $_POST['txtMessage']);
        
        $msgtype = "0"; // Always 0... yet..

        $sql = "INSERT INTO " . $CFG_TABLE['communication'] . " (gameID,fromID,toID,title,text,postDate,expireDate,ack,commType) ";
        $sql .= "VALUES ( $mGame , $fromPerson , $toPerson, '$msgtitle', '$msgtext', NOW( ) , NULL , '0', '$msgtype' );";
        mysqli_query($dbh, $sql) or die("can't do query: $sql");
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
				Message Recipient:<br>
				<input type="hidden" name="from" value="<?php echo $id; ?>">
				<select name="to" size="1">
<?php
					$to_get = isset($_GET['to']) ? $_GET['to'] : '';
					$tmpQuery="SELECT playerID, nick FROM " . $CFG_TABLE['players'] . " WHERE playerID <> ".(int)$id." ORDER BY nick ASC";
	                                $tmpPlayers = mysqli_query($dbh, $tmpQuery) or die("Sorry: $tmpQuery");
                                        while($tmpPlayer = mysqli_fetch_assoc($tmpPlayers))
                                        {
                                                if ($tmpPlayer['nick']){
						if($tmpPlayer['playerID'] == $to_get)
        	                                        echo("<option value='".$tmpPlayer['playerID']."' selected=\"selected\"> ".$tmpPlayer['nick']."</option>\n");
						else
                	                                echo("<option value='".$tmpPlayer['playerID']."'> ".$tmpPlayer['nick']."</option>\n");
						}
                                        }

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
