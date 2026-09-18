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

	/* load settings */
	if (!isset($_CONFIG))
		require 'config.php';
  require 'sessioncheck.php';

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

	/* connect to database */
	require 'connectdb.php';
?>
<!doctype html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(webchessTranslate("WebChess") . " :: " . webchessTranslate("Message View"));?></title>
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
        <a class="navbar-brand" href="mainmenu.php">WebChess</a>
        <div class="d-flex gap-2">
            <button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" onclick="toggleTheme()" data-title-dark="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>" data-title-light="<?php echo htmlspecialchars(webchessTranslate('Switch to Light Mode'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>">&#9790;</button>
            <a class="btn btn-outline-light btn-sm" href="mainmenu.php"><?php echo htmlspecialchars(webchessTranslate("Return to Main Menu"));?></a>
        </div>
    </div>
</nav>

<div class="container message-card">
    <div class="card shadow-sm">
        <div class="card-body">
            <?php
            if (isset($_POST['messageID'])) {
            				if (!webchessCsrfValidateRequest()) {
            					echo '<div class="alert alert-danger">' . htmlspecialchars(webchessTranslate("Invalid form token. Please reload and try again."), ENT_QUOTES, 'UTF-8') . '</div>';
            				} else {
                $messageID = (int) $_POST['messageID'];
                            $playerID = (int) $_SESSION['playerID'];

                            $stmtMessage = mysqli_prepare(
                                $dbh,
                                "SELECT * FROM " . $CFG_TABLE['communication'] . " WHERE commID = ? AND (toID IS NULL OR toID = ? OR fromID = ?)"
                            );
                            $tmpGames = false;
                            if ($stmtMessage) {
                                mysqli_stmt_bind_param($stmtMessage, "iii", $messageID, $playerID, $playerID);
                                mysqli_stmt_execute($stmtMessage);
                                $tmpGames = mysqli_stmt_get_result($stmtMessage);
                            }

                if (!$tmpGames || mysqli_num_rows($tmpGames) == 0) {
                    echo '<div class="alert alert-warning">' . htmlspecialchars(webchessTranslate("Message not found!")) . '</div>';
                } else {
                    while ($tmpGame = mysqli_fetch_assoc($tmpGames)) {
                        if ($tmpGame['fromID'] != 0) {
                                        $fromPlayerID = (int)$tmpGame['fromID'];
                                        $stmtNick = mysqli_prepare($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ? LIMIT 1");
                                        $FromPlayer = '';
                                        if ($stmtNick) {
                                            mysqli_stmt_bind_param($stmtNick, "i", $fromPlayerID);
                                            mysqli_stmt_execute($stmtNick);
                                            $innerRes = mysqli_stmt_get_result($stmtNick);
                                            $tempRes = $innerRes ? mysqli_fetch_assoc($innerRes) : null;
                                            $FromPlayer = $tempRes ? $tempRes['nick'] : '';
                                            mysqli_stmt_close($stmtNick);
                                        }
                        } else {
                            $FromPlayer = webchessTranslate("Webchess Administrator");
                        }
                        ?>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($tmpGame['title']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars(webchessTranslate("From:") . " " . $FromPlayer . " " . webchessTranslate("on") . " " . $tmpGame['postDate']); ?></small>
                            </div>
                            <div class="text-end">
                                <?php if ($tmpGame['fromID'] != 0) { ?>
                                    <button class="btn btn-sm btn-outline-primary me-1" type="button" onclick="MessagePlayer(<?php echo (int)$tmpGame['fromID']; ?>)"><?php echo htmlspecialchars(webchessTranslate("Reply")); ?></button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="HideMessage(<?php echo $messageID; ?>)"><?php echo htmlspecialchars(webchessTranslate("Archive")); ?></button>
                                <?php } ?>
                            </div>
                        </div>

                        <hr />
                        <div class="message-body mb-3"><?php echo nl2br(htmlspecialchars($tmpGame['text'])); ?></div>
                        <?php
                    }
                }

                if ($stmtMessage) {
                    mysqli_stmt_close($stmtMessage);
                }
				}
            } else {
                echo '<div class="alert alert-danger">' . htmlspecialchars(webchessTranslate("Message Error")) . '</div>';
                echo '<p>' . htmlspecialchars(webchessTranslate("An error ocurred!.")) . '</p>';
            }
            ?>
        </div>
    </div>
</div>

<form name="messageHideForm" action="mainmenu.php" method="post" style="display:none;">
    <input type="hidden" name="messageID" />
    <input type="hidden" name="ToDo" value="HideMessage" />
    <?php echo webchessCsrfField(); ?>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

