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

    error_log("--- chess.php Debug Start ---");
    error_log("SESSION playerID: " . (isset($_SESSION['playerID']) ? $_SESSION['playerID'] : 'NOT SET'));
    error_log("SESSION gameID: " . (isset($_SESSION['gameID']) ? $_SESSION['gameID'] : 'NOT SET'));

	/* load settings */
	if (!isset($_CONFIG))
		require 'config.php';

	/* define constants */
	require 'chessconstants.php';

	/* include outside functions */
	if (!isset($_CHESSUTILS))
		require 'chessutils.php';
  require 'lang.php';
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
    error_log("White Nick: " . $whiteNick);


	/* get Black's nick */
	$tmpNick = mysqli_query($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . ", " . $CFG_TABLE['games'] . " WHERE playerID = blackPlayer AND gameID = " . (int)$_SESSION['gameID']);
	$blackNick = mysqli_fetch_row($tmpNick)[0];
    error_log("Black Nick: " . $blackNick);


	/* load game */
	$isInCheck = (isset($_POST['isInCheck']) && $_POST['isInCheck'] == 'true');
	$isCheckMate = false;
	$isPromoting = false;
	$isUndoing = false;
	loadHistory();
	loadGame();
	processMessages();

    // Declare these variables as global to make them accessible in the main script scope
    global $isPlayersTurn, $currentPlayer, $opponentColor, $playersColor, $numMoves;

    error_log("numMoves after loadHistory(): " . $numMoves);
    error_log("playersColor after loadGame(): " . $playersColor);
    error_log("isPlayersTurn after calculation: " . ($isPlayersTurn ? 'true' : 'false'));
    error_log("currentPlayer after processMessages(): " . $currentPlayer);
    error_log("opponentColor after processMessages(): " . $opponentColor);


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
    error_log("--- chess.php Debug End ---");

	/* check whether the current player's king has already moved (castling no longer possible) */
  /* also check if both rooks have moved - castling is impossible then too */
	$_kingHasMoved  = false;
	$_rookMoveCount = 0;
	if ($numMoves >= 0)
	{
		foreach ($history as $_move)
		{
			if ($_move['curColor'] !== $playersColor)
				continue;

			if ($_move['curPiece'] === 'king')
				$_kingHasMoved = true;

			if ($_move['curPiece'] === 'rook')
				$_rookMoveCount++;
		}
	}
	/* castling hint is irrelevant once the king moved OR both rooks have moved */
	$_castlingPossible = !$_kingHasMoved && ($_rookMoveCount < 2);
?>
<!DOCTYPE html>
<html lang="<?php echo getGuiLanguage(); ?>">
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
            echo("<title>WebChess - " . webchessTranslate("Your Move") . "</title>\n");
        else
            echo("<title>WebChess - " . webchessTranslate("Opponent's Move") . "</title>\n");
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
        writeJSHistory();
        drawboard();
        echo 'var gameId = ' . (int)$_SESSION['gameID'] . ";\n";
        echo 'var gameLabel = ' . json_encode(webchessTranslate('Game')) . ";\n";
        echo 'var players = ' . json_encode($whiteNick . ' - ' . $blackNick) . ";\n";
        echo 'var playersColor = ' . json_encode($playersColor) . ";\n";
        echo 'var isPromoting = ' . json_encode((string)$isPromoting) . ";\n";
        echo 'var isKingInCheck = ' . json_encode((string)$isInCheck) . ";\n";
        echo 'var isGameOver = ' . json_encode((string)$isGameOver) . ";\n";
        echo 'var historyLayout = ' . json_encode($_SESSION['pref_historylayout']) . ";\n";

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
        echo('<script type="text/javascript" src="javascript/isCheckMate.js"></script>');
    if(!isBoardDisabled() || $_SESSION['isSharedPC'])
        echo('<script type="text/javascript" src="javascript/squareclicked.js"></script>');
    ?>
    <script type="text/javascript" src="javascript/board.js"></script>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h5">WebChess</span>
        <div class="text-light small">
            <span id="players"></span>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button id="theme-toggle-btn" class="btn btn-outline-light btn-sm" type="button" onclick="toggleTheme()" title="<?php echo webchessTranslate('Toggle Dark Mode'); ?>">Theme</button>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-lg-5 pb-5">
    <div class="row g-4">
        <!-- Chess Board Section -->
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-3">
                    <form name="gamedata" method="post" action="chess.php">
                        <?php
                            if ($isPromoting && (!$isPlayersTurn || $_SESSION['isSharedPC']))
                                writePromotion();
                            if (isset($isUndoRequested) && $isUndoRequested)
                                writeUndoRequest();
                            if (isset($isDrawRequested) && $isDrawRequested)
                                writeDrawRequest();
                        ?>
                        <div id="chessboard" class="text-center mb-3"></div>

                        <div id="moveinfo" class="alert alert-secondary py-2 mb-3 text-center">
                            <span id="curmove" class="fw-bold me-3"></span>
                            <span id="whosmove" class="badge bg-primary"></span>
                        </div>

                        <div id="gamebuttons" class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                            <input type="button" id="btnUndo" class="btn btn-warning btn-sm" value="<?php echo webchessTranslate('Request Undo'); ?>" disabled="disabled" onclick="undo()" />
                            <input type="button" id="btnDraw" class="btn btn-info btn-sm" value="<?php echo webchessTranslate('Request Draw'); ?>" disabled="disabled" onclick="draw()" />
                            <input type="button" id="btnResign" class="btn btn-danger btn-sm" value="<?php echo webchessTranslate('Resign'); ?>" disabled="disabled" onclick="resigngame()" />
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

                    <?php if ($_castlingPossible): ?>
                    <div class="text-muted small text-center mb-3">
                        <?php echo webchessTranslate('When castling, just move the king (the rook will move automatically).'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-dark border-0 text-white">
                    <h6 class="mb-0"><?php echo webchessTranslate('Captured Pieces'); ?></h6>
                </div>
                <div class="card-body">
                    <div id="captures" class="text-center"></div>
                </div>
            </div>

            <div id="gamenav" class="mt-4"></div>
        </div>

        <!-- Info & Controls Section -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white border-0">
                    <h5 class="mb-0"><?php echo webchessTranslate('Game Information'); ?></h5>
                </div>
                <div class="card-body">
                    <div id="gameid" class="mb-3 small text-muted"></div>

                    <div class="alert alert-info mb-3" id="statusmsg"></div>

                    <div id="checkmsg" class="alert alert-danger d-none mb-3"></div>

                    <h6 class="border-bottom pb-2 mb-3"><?php echo webchessTranslate('Move History'); ?></h6>
                    <div id="gamebody" class="overflow-auto" style="max-height: 350px;"></div>

                    <div class="mt-4 pt-3 border-top d-grid gap-2">
                        <input type="button" id="btnMainMenu" class="btn btn-outline-primary" value="<?php echo webchessTranslate('Main Menu'); ?>" disabled="disabled" onclick="displayMainmenu()" />
                        <input type="button" id="btnReload" class="btn btn-outline-secondary" value="<?php echo webchessTranslate('Reload Board'); ?>" disabled="disabled" onclick="reloadPage(this)" />
                        <input type="button" id="btnPGN" class="btn btn-outline-success" value="<?php echo webchessTranslate('Download PGN'); ?>" disabled="disabled" onclick="downloadPGN()" />
                        <input type="button" id="btnLogout" class="btn btn-outline-danger" value="<?php echo webchessTranslate('Logout'); ?>" disabled="disabled" onclick="logout()" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form name="gamemenu" method="post" action="chess.php" style="display:none;">
    <input type="hidden" name="ToDo" value="Logout" />
</form>

<noscript>
    <div class="container mt-3">
        <div class="alert alert-danger text-center">
            Warning: <?php echo webchessTranslate('JavaScript must be enabled for WebChess to work properly!'); ?>
        </div>
    </div>
</noscript>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript">
    // Ensure buttons are enabled and working
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var btnMainMenu = document.getElementById('btnMainMenu');
            var btnReload = document.getElementById('btnReload');
            var btnPGN = document.getElementById('btnPGN');
            var btnLogout = document.getElementById('btnLogout');
            var btnUndo = document.getElementById('btnUndo');
            var btnDraw = document.getElementById('btnDraw');
            var btnResign = document.getElementById('btnResign');

            if (btnMainMenu) btnMainMenu.disabled = false;
            if (btnReload) btnReload.disabled = false;
            if (btnPGN) btnPGN.disabled = false;
            if (btnLogout) btnLogout.disabled = false;

            // Enable game buttons if not player's turn
            if (btnUndo) btnUndo.disabled = false;
            if (btnDraw) btnDraw.disabled = false;
            if (btnResign) btnResign.disabled = false;
        }, 100);
    });
</script>
</body>
</html>
