<?php
// $Id: chess.php,v 1.13 2010/08/18 09:38:56 sandking Exp $

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

	/* define constants */
	require 'chessconstants.php';

	/* include outside functions */
	if (!isset($_CHESSUTILS))
		require 'chessutils.php';
	require 'gui.php';
	require 'chessdb.php';
	require 'move.php';
	require 'undo.php';

	/* allow WebChess to be run on PHP systems < 4.1.0, using old http vars */
	fixOldPHPVersions();

	/* check session status */
	require 'sessioncheck.php';

	/* check if loading game */
	if (isset($_POST['gameID']))
		$_SESSION['gameID'] = $_POST['gameID'];

	/* debug flag */
	define ("DEBUG", 0);

	/* connect to database */
	require 'connectdb.php';

	/* get White's nick */
	$tmpNick = mysqli_query($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . ", " . $CFG_TABLE['games'] . " WHERE playerID = whitePlayer AND gameID = " . (int)$_SESSION['gameID']);
	$whiteNick = mysqli_fetch_row($tmpNick)[0];

	/* get Black's nick */
	$tmpNick = mysqli_query($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . ", " . $CFG_TABLE['games'] . " WHERE playerID = blackPlayer AND gameID = " . (int)$_SESSION['gameID']);
	$blackNick = mysqli_fetch_row($tmpNick)[0];

	/* load game */
	$isInCheck = (isset($_POST['isInCheck']) && $_POST['isInCheck'] == 'true');
	$isCheckMate = false;
	$isPromoting = false;
	$isUndoing = false;
	loadHistory();
	loadGame();
	processMessages();

	if ($isUndoing)
	{
		doUndo();
		saveGame();
	}
	elseif ((isset($_POST['promotion']) && $_POST['promotion'] != "") && (isset($_POST['toRow']) && $_POST['toRow'] != "") && (isset($_POST['toCol']) && $_POST['toCol'] != ""))
	{
		savePromotion();
		$board[$_POST['toRow']][$_POST['toCol']] = $_POST['promotion'] | ($board[$_POST['toRow']][$_POST['toCol']] & BLACK);
		saveGame();
	}
	elseif ((isset($_POST['fromRow']) && $_POST['fromRow'] != "") && (isset($_POST['fromCol']) && $_POST['fromCol'] != "") && (isset($_POST['toRow']) && $_POST['toRow'] != "") && (isset($_POST['toCol']) && $_POST['toCol'] != ""))
	{
		/* ensure it's the current player moving				 */
		/* NOTE: if not, this will currently ignore the command...               */
		/*       perhaps the status should be instead?                           */
		/*       (Could be confusing to player if they double-click or something */
		$tmpIsValid = true;
		if (($numMoves == -1) || ($numMoves % 2 == 1))
		{
			/* White's move... ensure that piece being moved is white */
			if ((($board[$_POST['fromRow']][$_POST['fromCol']] & BLACK) != 0) || ($board[$_POST['fromRow']][$_POST['fromCol']] == 0))
				/* invalid move */
				$tmpIsValid = false;
		}
		else
		{
			/* Black's move... ensure that piece being moved is black */
			if ((($board[$_POST['fromRow']][$_POST['fromCol']] & BLACK) != BLACK) || ($board[$_POST['fromRow']][$_POST['fromCol']] == 0))
				/* invalid move */
				$tmpIsValid = false;
		}

		if ($tmpIsValid)
		{
			saveHistory();
			doMove();
			saveGame();
		}
	}
	elseif(isset($history[$numMoves]) && $history[$numMoves]['curPiece'] == 'pawn' && $history[$numMoves]['promotedTo'] == null)
	{	// Incomplete promotion?
		if($history[$numMoves]['toRow'] == 7 || $history[$numMoves]['toRow'] == 0)
		{
			$isPromoting = true;
		}
	}

	mysqli_close($dbh);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="ISO-8859-1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="pragma" content="no-cache" />
    <!-- Use inline script to prevent theme flicker on reload -->
    <script type="text/javascript">
        (function() {
            var theme = localStorage.getItem('webchess-theme');
            if (!theme) {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    theme = 'dark';
                } else {
                    theme = 'light';
                }
            }
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/chess.css" type="text/css" />
    <?php
        echo("<link rel='stylesheet' href='images/");
        echo($_SESSION['pref_theme'] . "/wctheme.css' type='text/css' />\n");

        /* find out if it's the current player's turn */
        if (( (($numMoves == -1) || (($numMoves % 2) == 1)) && ($playersColor == "white"))
                || ((($numMoves % 2) == 0) && ($playersColor == "black")))
            $isPlayersTurn = true;
        else
            $isPlayersTurn = false;

        if ($_SESSION['isSharedPC'])
            echo("<title>WebChess</title>\n");
        else if ($isPlayersTurn)
            echo("<title>WebChess - Your Move</title>\n");
        else
            echo("<title>WebChess - Opponent's Move</title>\n");
    ?>
    <link rel="stylesheet" href="styles/theme.css" type="text/css" />
    <script type="text/javascript" src="javascript/theme.js"></script>
    <script type="text/javascript">
    <?php
        echo("var cfgImageExt = '$CFG_IMAGE_EXT';\n");
        /* transfer board data to javacript */
        writeJSboard();
        /* if it's not the player's turn, enable auto-refresh */
        $autoRefresh = !$isPlayersTurn && !isBoardDisabled() && !$_SESSION['isSharedPC'];
        echo('var autoreload = ');
        if(!$autoRefresh)
            echo('0');
        else if ($_SESSION['pref_autoreload'] >= $CFG_MINAUTORELOAD)
            echo ($_SESSION['pref_autoreload']);
        else
            echo ($CFG_MINAUTORELOAD);
        echo(";\n");
        writeJShistory();
        drawboard();
        echo 'var gameId = ' . (int)$_SESSION['gameID'] . ";\n";
        echo 'var players = "' . $whiteNick . ' - ' . $blackNick . "\";\n";
        echo 'var playersColor = "' . $playersColor . "\";\n";
        echo 'var isPromoting = "'.$isPromoting. "\";\n";
        echo 'var isKingInCheck = "'.$isInCheck. "\";\n";
        echo 'var isGameOver = "'.$isGameOver. "\";\n";
        echo 'var historyLayout = "'.$_SESSION['pref_historylayout']. "\";\n";

        writeStatus();
        writeHistory();
        // Captured pieces..
        require 'capt.php';
    ?>
    </script>
    <script type="text/javascript" src="javascript/chessutils.js"></script>
    <script type="text/javascript" src="javascript/commands.js"></script>
    <script type="text/javascript" src="javascript/validation.js"></script>
    <?php
    if($isPlayersTurn || $_SESSION['isSharedPC'] || $isPromoting)
        echo('
    <script type="text/javascript" src="javascript/isCheckMate.js"></script>');
    if(!isBoardDisabled() || $_SESSION['isSharedPC'])
        echo('
    <script type="text/javascript" src="javascript/squareclicked.js"></script>');
    ?>
    <script type="text/javascript" src="javascript/board.js"></script>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="mainmenu.php">WEBCHESS</a>
        <div class="navbar-text text-light">
            <?php echo $whiteNick; ?> vs <?php echo $blackNick; ?> (Game #<?php echo $_SESSION['gameID']; ?>)
        </div>
        <div class="ms-auto d-flex align-items-center">
            <button id="theme-toggle-btn" class="btn btn-outline-light btn-sm me-3" type="button" onclick="toggleTheme()" title="Toggle Dark Mode">🌙</button>
            <button class="btn btn-outline-danger btn-sm" onclick="logout()">Logout</button>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row">
        <!-- Chess Board Section -->
        <div class="col-lg-7 text-center">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form name="gamedata" method="post" action="chess.php">
                        <?php
                            if ($isPromoting && (!$isPlayersTurn || $_SESSION['isSharedPC']))
                                writePromotion();
                            if (isset($isUndoRequested) && $isUndoRequested)
                                writeUndoRequest();
                            if (isset($isDrawRequested) && $isDrawRequested)
                                writeDrawRequest();
                        ?>
                        <div id="chessboard" class="d-inline-block mb-3"></div>

                        <div id="moveinfo" class="alert alert-secondary py-2 mb-3">
                            <span id="curmove" class="fw-bold me-3"></span>
                            <span id="whosmove" class="badge bg-primary"></span>
                        </div>

                        <div id="gamebuttons" class="d-flex justify-content-center gap-2 mb-3">
                            <button type="button" id="btnUndo" class="btn btn-warning shadow-sm" disabled="disabled">Request Undo</button>
                            <button type="button" id="btnDraw" class="btn btn-info shadow-sm" disabled="disabled">Request Draw</button>
                            <button type="button" id="btnResign" class="btn btn-danger shadow-sm" disabled="disabled">Resign</button>
                        </div>

                        <input type="hidden" name="requestUndo" value="no" />
                        <input type="hidden" name="requestDraw" value="no" />
                        <input type="hidden" name="resign" value="no" />
                        <input type="hidden" name="fromRow" value="<?php if ($isPromoting) echo ($_POST['fromRow']); ?>" />
                        <input type="hidden" name="fromCol" value="<?php if ($isPromoting) echo ($_POST['fromCol']); ?>" />
                        <input type="hidden" name="toRow" value="<?php if ($isPromoting) echo ($_POST['toRow']); ?>" />
                        <input type="hidden" name="toCol" value="<?php if ($isPromoting) echo ($_POST['toCol']); ?>" />
                        <input type="hidden" name="isInCheck" value="false" />
                        <input type="hidden" name="isCheckMate" value="false" />
                    </form>

                    <div id="gamenav" class="mb-3"></div>

                    <div class="text-muted small mb-3">
                        When castling, just move the king (the rook will move automatically).
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white py-2">
                    <h6 class="mb-0">Captured Pieces</h6>
                </div>
                <div class="card-body py-3">
                    <div id="captures"></div>
                </div>
            </div>
        </div>

        <!-- Info & Controls Section -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4 h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Game Info</h5>
                </div>
                <div class="card-body">
                    <div id="checkmsg" class="text-danger fw-bold mb-2"></div>
                    <div id="statusmsg" class="text-info mb-3"></div>

                    <h6 class="border-bottom pb-2 mb-3">Moves History</h6>
                    <div id="gamebody" class="overflow-auto" style="max-height: 400px; font-family: monospace;"></div>

                    <div class="mt-4 pt-3 border-top d-grid gap-2">
                        <button id="btnMainMenu" class="btn btn-outline-primary" disabled="disabled">Main Menu</button>
                        <button id="btnReload" class="btn btn-outline-secondary" disabled="disabled">Reload Board</button>
                        <button id="btnPGN" class="btn btn-outline-success" disabled="disabled">Download PGN</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form name="gamemenu" method="post" action="chess.php">
    <input type="hidden" name="ToDo" value="Logout" />
</form>

<noscript>
    <div class="container mt-3">
        <div class="alert alert-danger text-center">
            !Warning! Javascript must be enabled for proper operation of WebChess
        </div>
    </div>
</noscript>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
