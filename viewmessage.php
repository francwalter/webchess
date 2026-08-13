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
?>
<!doctype html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(gettext("WebChess") . " :: " . gettext("Message View"));?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/theme.css" type="text/css" />
    <script type="text/javascript" src="javascript/theme.js"></script>
    <script type="text/javascript" src="javascript/messages.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .message-card { max-width: 900px; margin: 2.5rem auto; }
        .message-body { white-space: pre-wrap; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="mainmenu.php">♔ WebChess</a>
        <div class="d-flex gap-2">
            <button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" onclick="toggleTheme()">🌙</button>
            <a class="btn btn-outline-light btn-sm" href="mainmenu.php"><?php echo htmlspecialchars(gettext("Return to Main Menu"));?></a>
        </div>
    </div>
</nav>

<div class="container message-card">
    <div class="card shadow-sm">
        <div class="card-body">
            <?php
            if (isset($_POST['messageID'])) {
                $messageID = (int) $_POST['messageID'];

                $SqlQuery = "SELECT * FROM " . $CFG_TABLE['communication'] . " WHERE commID = " . $messageID;
                $tmpGames = mysqli_query($dbh, $SqlQuery);

                if (!$tmpGames || mysqli_num_rows($tmpGames) == 0) {
                    echo '<div class="alert alert-warning">' . htmlspecialchars(gettext("Message not found!")) . '</div>';
                } else {
                    while ($tmpGame = mysqli_fetch_assoc($tmpGames)) {
                        if ($tmpGame['fromID'] != 0) {
                            $innerSQL = "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = " . (int)$tmpGame['fromID'];
                            $innerRes = mysqli_query($dbh, $innerSQL);
                            $tempRes = mysqli_fetch_assoc($innerRes);
                            $FromPlayer = $tempRes['nick'];
                        } else {
                            $FromPlayer = gettext("Webchess Administrator");
                        }
                        ?>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($tmpGame['title']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars(gettext("From:") . " " . $FromPlayer . " " . gettext("on") . " " . $tmpGame['postDate']); ?></small>
                            </div>
                            <div class="text-end">
                                <?php if ($tmpGame['fromID'] != 0) { ?>
                                    <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="MessagePlayer(<?php echo (int)$tmpGame['fromID']; ?>)"><?php echo htmlspecialchars(gettext("Reply")); ?></button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="HideMessage(<?php echo $messageID; ?>)"><?php echo htmlspecialchars(gettext("Archive")); ?></button>
                                <?php } ?>
                            </div>
                        </div>

                        <hr />
                        <div class="message-body mb-3"><?php echo nl2br(htmlspecialchars($tmpGame['text'])); ?></div>
                        <?php
                    }
                }
            } else {
                echo '<div class="alert alert-danger">' . htmlspecialchars(gettext("Message Error")) . '</div>';
                echo '<p>' . htmlspecialchars(gettext("An error ocurred!.")) . '</p>';
            }
            ?>
        </div>
    </div>
</div>

<form name="messageHideForm" action="mainmenu.php" method="post" style="display:none;">
    <input type="hidden" name="messageID" />
    <input type="hidden" name="ToDo" value="HideMessage" />
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
