<?php
// $Id: openpgn.php,v 1.3 2010/08/14 16:57:54 sandking Exp $

/*
    This file is part of WebChess. http://webchess.sourceforge.net
	Copyright 2010 Jonathan Evraire, Rodrigo Flores, Dadi Jonsson

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
	require 'chessutils.php';
	require 'gui.php';
	require 'chessdb.php';

	/* ensure compatibility helpers are available (older code paths removed) */

	/* check session status */
	require 'sessioncheck.php';

	/* debug flag */
	define ("DEBUG", 0);

				/* connect to database */
				require 'connectdb.php';

				/* validate session and game selection */
				if (!isset($_SESSION['gameID']) || !is_numeric($_SESSION['gameID'])) {
					// Friendly HTML response when no game is selected in session
					header('Content-Type: text/html; charset=utf-8');
					echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PGN Download</title>';
					echo '<meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
					echo '<div style="max-width:800px;margin:3rem auto;font-family:Arial,Helvetica,sans-serif;">';
					echo '<h2>Kein Spiel ausgewählt</h2>';
					echo '<p>Es wurde kein gültiges Spiel in Ihrer Session gefunden. Bitte öffnen Sie ein Spiel und versuchen Sie es erneut.</p>';
					echo '<p><a href="mainmenu.php">Zurück zum Hauptmenü</a></p>';
					echo '</div></body></html>';
					exit;
				}

				$gid = (int)$_SESSION['gameID'];
				$chk = mysqli_query($dbh, "SELECT gameID FROM " . $CFG_TABLE['games'] . " WHERE gameID = " . $gid);
				if (!$chk || mysqli_num_rows($chk) == 0) {
					header('Content-Type: text/html; charset=utf-8');
					echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PGN Download</title>';
					echo '<meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
					echo '<div style="max-width:800px;margin:3rem auto;font-family:Arial,Helvetica,sans-serif;">';
					echo '<h2>Spiel nicht gefunden</h2>';
					echo '<p>Das angeforderte Spiel existiert nicht oder ist nicht mehr verfügbar.</p>';
					echo '<p><a href="mainmenu.php">Zurück zum Hauptmenü</a></p>';
					echo '</div></body></html>';
					exit;
				}

				$output_file = 'game' . $gid . '.pgn';

header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP 1.1
header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP 1.1
header('Pragma: no-cache'); // HTTP 1.0
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s') . 'GMT');
header('Content-Transfer-Encoding: none');
header('Content-Type: application/x-chess-pgn; name="'. $output_file . '"');
header('Content-Disposition: attachment; filename="' .$output_file . '"');
// header("Content-length: $content_len");

/* load history and write PGN for the current game */
loadHistory();
ReturnGameInfo((int)$_SESSION['gameID']);
writePGN();

/* close mysqli connection (connectdb.php exposes $dbh) */
if (isset($dbh) && is_object($dbh)) {
	mysqli_close($dbh);
}
