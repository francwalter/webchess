<?php
// $Id: chessdb.php,v 1.10 2010/08/14 16:57:54 sandking Exp $

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

	/* these functions are used to interact with the DB */
	function updateTimestamp()
	{
		global $CFG_TABLE, $dbh;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		$gameID = (int)$_SESSION['gameID'];
		$stmtTimestamp = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET lastMove = NOW() WHERE gameID = ?");
		if ($stmtTimestamp)
		{
			mysqli_stmt_bind_param($stmtTimestamp, "i", $gameID);
			mysqli_stmt_execute($stmtTimestamp);
			mysqli_stmt_close($stmtTimestamp);
		}
	}

	function loadHistory()
	{
		global $CFG_TABLE, $dbh;
		global $history, $numMoves;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		$allMoves = false;
		$gameID = (int)$_SESSION['gameID'];
		$stmtHistory = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['history'] . " WHERE gameID = ? ORDER BY timeOfMove");
		if ($stmtHistory)
		{
			mysqli_stmt_bind_param($stmtHistory, "i", $gameID);
			mysqli_stmt_execute($stmtHistory);
			$allMoves = mysqli_stmt_get_result($stmtHistory);
			mysqli_stmt_close($stmtHistory);
		}

		$numMoves = -1;
		while ($thisMove = mysqli_fetch_assoc($allMoves))
		{
			$numMoves++;
			$history[$numMoves] = $thisMove;
		}
	}

	function savePromotion()
	{
		global $CFG_TABLE, $dbh;
		global $history, $numMoves, $isInCheck, $CFG_USEEMAILNOTIFICATION;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		if ($isInCheck)
		{
			$tmpIsInCheck = 1;
			$history[$numMoves]['isInCheck'] = 1;
		}
		else
			$tmpIsInCheck = 0;

		$history[$numMoves]['promotedTo'] = getPieceName($_POST['promotion']);

		$promotedTo = getPieceName($_POST['promotion']);
		$timeOfMove = (string)$history[$numMoves]['timeOfMove'];
		$gameID = (int)$_SESSION['gameID'];
		$stmtPromotion = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['history'] . " SET promotedTo = ?, isInCheck = ? WHERE gameID = ? AND timeOfMove = ?");
		if ($stmtPromotion)
		{
			mysqli_stmt_bind_param($stmtPromotion, "siis", $promotedTo, $tmpIsInCheck, $gameID, $timeOfMove);
			mysqli_stmt_execute($stmtPromotion);
			mysqli_stmt_close($stmtPromotion);
		}

		updateTimestamp();

		/* if email notification is activated and move does not result in a pawn's promotion... */
		if ($CFG_USEEMAILNOTIFICATION && ! $_SESSION['isSharedPC'])
		{
			if ($history[$numMoves]['replaced'] == null)
				$tmpReplaced = '';
			else
				$tmpReplaced = $history[$numMoves]['replaced'];

			/* get opponent's color */
			if (($numMoves == -1) || ($numMoves % 2 == 1))
				$oppColor = "black";
			else
				$oppColor = "white";

			/* get opponent's player ID */
			$opponentID = 0;
			$gameID = (int)$_SESSION['gameID'];
			if ($oppColor == 'white')
				$stmtOpponentID = mysqli_prepare($dbh, "SELECT whitePlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
			else
				$stmtOpponentID = mysqli_prepare($dbh, "SELECT blackPlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
			if (isset($stmtOpponentID) && $stmtOpponentID)
			{
				mysqli_stmt_bind_param($stmtOpponentID, "i", $gameID);
				mysqli_stmt_execute($stmtOpponentID);
				$tmpOpponentID = mysqli_stmt_get_result($stmtOpponentID);
				$opponentID = ($tmpOpponentID && ($rowOpponent = mysqli_fetch_row($tmpOpponentID))) ? (int)$rowOpponent[0] : 0;
				mysqli_stmt_close($stmtOpponentID);
			}

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
					/* get opponent's nick */
					$opponentNick = '';
					$stmtOpponentNick = mysqli_prepare($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ?");
					if ($stmtOpponentNick)
					{
						$playerID = (int)$_SESSION['playerID'];
						mysqli_stmt_bind_param($stmtOpponentNick, "i", $playerID);
						mysqli_stmt_execute($stmtOpponentNick);
						$tmpOpponentNick = mysqli_stmt_get_result($stmtOpponentNick);
						$opponentNick = ($tmpOpponentNick && ($rowNick = mysqli_fetch_row($tmpOpponentNick))) ? (string)$rowNick[0] : '';
						mysqli_stmt_close($stmtOpponentNick);
					}

					/* get opponent's prefered history type */
					$stmtOpponentHistory = mysqli_prepare($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ? AND preference = 'history'");
					$tmpOpponentHistory = false;
					if ($stmtOpponentHistory)
					{
						mysqli_stmt_bind_param($stmtOpponentHistory, "i", $opponentID);
						mysqli_stmt_execute($stmtOpponentHistory);
						$tmpOpponentHistory = mysqli_stmt_get_result($stmtOpponentHistory);
						mysqli_stmt_close($stmtOpponentHistory);
					}

					/* default to PGN */
					if (mysqli_num_rows($tmpOpponentHistory) > 0)
						$opponentHistory = mysqli_fetch_row($tmpOpponentHistory)[0];
					else
						$opponentHistory = 'pgn';

					/* notify opponent of move via email */
					if ($opponentHistory == 'pgn')
						webchessMail('move', $opponentEmail, moveToPGNString($history[$numMoves]['curColor'], $history[$numMoves]['curPiece'], $history[$numMoves]['fromRow'], $history[$numMoves]['fromCol'], $history[$numMoves]['toRow'], $history[$numMoves]['toCol'], $tmpReplaced, $history[$numMoves]['promotedTo'], $isInCheck), $opponentNick, $_SESSION['gameID']);
					else
						webchessMail('move', $opponentEmail, moveToVerbousString($history[$numMoves]['curColor'], $history[$numMoves]['curPiece'], $history[$numMoves]['fromRow'], $history[$numMoves]['fromCol'], $history[$numMoves]['toRow'], $history[$numMoves]['toCol'], $tmpReplaced, '', $isInCheck), $opponentNick, $_SESSION['gameID']);
				}
			}
		}
	}

	function saveHistory()
	{
		global $CFG_TABLE, $dbh;
		global $board, $isPromoting, $history, $numMoves, $isInCheck, $CFG_USEEMAILNOTIFICATION;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		/* set destination row for pawn promotion */
		if ($board[$_POST['fromRow']][$_POST['fromCol']] & BLACK)
			$targetRow = 0;
		else
			$targetRow = 7;

		/* determine if move results in pawn promotion */
		if ((($board[$_POST['fromRow']][$_POST['fromCol']] & COLOR_MASK) == PAWN) && ($_POST['toRow'] == $targetRow))
			$isPromoting = true;
		else
			$isPromoting = false;

		/* determine who's playing based on number of moves so far */
		if (($numMoves == -1) || ($numMoves % 2 == 1))
		{
			$curColor = "white";
			$oppColor = "black";
		}
		else
		{
			$curColor = "black";
			$oppColor = "white";
		}

		/* add move to history */
		$numMoves++;
		$history[$numMoves]['gameID'] = $_SESSION['gameID'];
		$history[$numMoves]['curPiece'] = getPieceName($board[$_POST['fromRow']][$_POST['fromCol']]);
		$history[$numMoves]['curColor'] = $curColor;
		$history[$numMoves]['fromRow'] = $_POST['fromRow'];
		$history[$numMoves]['fromCol'] = $_POST['fromCol'];
		$history[$numMoves]['toRow'] = $_POST['toRow'];
		$history[$numMoves]['toCol'] = $_POST['toCol'];
		$history[$numMoves]['promotedTo'] = null;

		if ($isInCheck)
			$history[$numMoves]['isInCheck'] = 1;
		else
			$history[$numMoves]['isInCheck'] = 0;

		if (DEBUG)
		{
			if ($history[$numMoves]['curPiece'] == '')
				echo ("WARNING!!!  missing piece at ".$_POST['fromRow'].", ".$_POST['fromCol'].": ".$board[$_POST['fromRow']][$_POST['fromCol']]."<p>\n");
		}

		$historyGameID = (int)$_SESSION['gameID'];
		$fromRow = (int)$_POST['fromRow'];
		$fromCol = (int)$_POST['fromCol'];
		$toRow = (int)$_POST['toRow'];
		$toCol = (int)$_POST['toCol'];
		$isInCheckInt = (int)$history[$numMoves]['isInCheck'];
		$curPieceName = getPieceName($board[$fromRow][$fromCol]);

		if ($board[$toRow][$toCol] == 0)
		{
			$stmtHistory = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['history'] . " (timeOfMove, gameID, curPiece, curColor, fromRow, fromCol, toRow, toCol, replaced, promotedTo, isInCheck) VALUES (Now(), ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)");
			if ($stmtHistory)
			{
				mysqli_stmt_bind_param($stmtHistory, "issiiiii", $historyGameID, $curPieceName, $curColor, $fromRow, $fromCol, $toRow, $toCol, $isInCheckInt);
				mysqli_stmt_execute($stmtHistory);
				mysqli_stmt_close($stmtHistory);
			}
			$history[$numMoves]['replaced'] = null;
			$tmpReplaced = "";
		}
		else
		{
			$replacedPieceName = getPieceName($board[$toRow][$toCol]);
			$stmtHistory = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['history'] . " (timeOfMove, gameID, curPiece, curColor, fromRow, fromCol, toRow, toCol, replaced, promotedTo, isInCheck) VALUES (Now(), ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)");
			if ($stmtHistory)
			{
				mysqli_stmt_bind_param($stmtHistory, "issiiiisi", $historyGameID, $curPieceName, $curColor, $fromRow, $fromCol, $toRow, $toCol, $replacedPieceName, $isInCheckInt);
				mysqli_stmt_execute($stmtHistory);
				mysqli_stmt_close($stmtHistory);
			}

			$history[$numMoves]['replaced'] = $replacedPieceName;
			$tmpReplaced = $history[$numMoves]['replaced'];
		}


		/* if email notification is activated and move does not result in a pawn's promotion... */
		/* NOTE: moves resulting in pawn promotion are handled by savePromotion() above */
		if ($CFG_USEEMAILNOTIFICATION && !$isPromoting && ! $_SESSION['isSharedPC'])
		{
			/* get opponent's player ID */
			$opponentID = 0;
			$gameID = (int)$_SESSION['gameID'];
			if ($oppColor == 'white')
				$stmtOpponentID = mysqli_prepare($dbh, "SELECT whitePlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
			else
				$stmtOpponentID = mysqli_prepare($dbh, "SELECT blackPlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
			if (isset($stmtOpponentID) && $stmtOpponentID)
			{
				mysqli_stmt_bind_param($stmtOpponentID, "i", $gameID);
				mysqli_stmt_execute($stmtOpponentID);
				$tmpOpponentID = mysqli_stmt_get_result($stmtOpponentID);
				$opponentID = ($tmpOpponentID && ($rowOpponent = mysqli_fetch_row($tmpOpponentID))) ? (int)$rowOpponent[0] : 0;
				mysqli_stmt_close($stmtOpponentID);
			}

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
					/* get opponent's nick */
					$opponentNick = '';
					$stmtOpponentNick = mysqli_prepare($dbh, "SELECT nick FROM " . $CFG_TABLE['players'] . " WHERE playerID = ?");
					if ($stmtOpponentNick)
					{
						$playerID = (int)$_SESSION['playerID'];
						mysqli_stmt_bind_param($stmtOpponentNick, "i", $playerID);
						mysqli_stmt_execute($stmtOpponentNick);
						$tmpOpponentNick = mysqli_stmt_get_result($stmtOpponentNick);
						$opponentNick = ($tmpOpponentNick && ($rowNick = mysqli_fetch_row($tmpOpponentNick))) ? (string)$rowNick[0] : '';
						mysqli_stmt_close($stmtOpponentNick);
					}

					/* get opponent's prefered history type */
					$stmtOpponentHistory = mysqli_prepare($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ? AND preference = 'history'");
					$tmpOpponentHistory = false;
					if ($stmtOpponentHistory)
					{
						mysqli_stmt_bind_param($stmtOpponentHistory, "i", $opponentID);
						mysqli_stmt_execute($stmtOpponentHistory);
						$tmpOpponentHistory = mysqli_stmt_get_result($stmtOpponentHistory);
						mysqli_stmt_close($stmtOpponentHistory);
					}

					/* default to PGN */
					if (mysqli_num_rows($tmpOpponentHistory) > 0)
						$opponentHistory = mysqli_fetch_row($tmpOpponentHistory)[0];
					else
						$opponentHistory = 'pgn';

					/* notify opponent of move via email */
					if ($opponentHistory == 'pgn')
						webchessMail('move', $opponentEmail, moveToPGNString($history[$numMoves]['curColor'], $history[$numMoves]['curPiece'], $history[$numMoves]['fromRow'], $history[$numMoves]['fromCol'], $history[$numMoves]['toRow'], $history[$numMoves]['toCol'], $tmpReplaced, '', $isInCheck), $opponentNick, $_SESSION['gameID']);
					else
						webchessMail('move', $opponentEmail, moveToVerbousString($history[$numMoves]['curColor'], $history[$numMoves]['curPiece'], $history[$numMoves]['fromRow'], $history[$numMoves]['fromCol'], $history[$numMoves]['toRow'], $history[$numMoves]['toCol'], $tmpReplaced, '', $isInCheck), $opponentNick, $_SESSION['gameID']);
				}
			}
		}
	}

	function loadGame()
	{
		global $CFG_TABLE, $dbh;
		global $board, $playersColor;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		/* clear board data */
		for ($i = 0; $i < 8; $i++)
			for ($j = 0; $j < 8; $j++)
				$board[$i][$j] = 0;

		/* get data from database */
		$pieces = false;
		$gameID = (int)$_SESSION['gameID'];
		$stmtPieces = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['pieces'] . " WHERE gameID = ?");
		if ($stmtPieces)
		{
			mysqli_stmt_bind_param($stmtPieces, "i", $gameID);
			mysqli_stmt_execute($stmtPieces);
			$pieces = mysqli_stmt_get_result($stmtPieces);
			mysqli_stmt_close($stmtPieces);
		}

		/* setup board */
		while ($thisPiece = mysqli_fetch_assoc($pieces))
		{
			$board[$thisPiece["row"]][$thisPiece["col"]] = getPieceCode($thisPiece["color"], $thisPiece["piece"]);
		}

		/* get current player's color */
		$tmpTurn = null;
		$stmtTurns = mysqli_prepare($dbh, "SELECT whitePlayer, blackPlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
		if ($stmtTurns)
		{
			mysqli_stmt_bind_param($stmtTurns, "i", $gameID);
			mysqli_stmt_execute($stmtTurns);
			$tmpTurns = mysqli_stmt_get_result($stmtTurns);
			$tmpTurn = $tmpTurns ? mysqli_fetch_assoc($tmpTurns) : null;
			mysqli_stmt_close($stmtTurns);
		}
		if (!$tmpTurn)
			return;

		if ($tmpTurn['whitePlayer'] == $_SESSION['playerID'])
			$playersColor = "white";
		else
			$playersColor = "black";
	}

	function saveGame()
	{
		global $CFG_TABLE, $dbh;
		global $board, $playersColor;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		/* clear old data */
		$saveGameID = (int)$_SESSION['gameID'];
		$stmtDeletePieces = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['pieces'] . " WHERE gameID = ?");
		if ($stmtDeletePieces)
		{
			mysqli_stmt_bind_param($stmtDeletePieces, "i", $saveGameID);
			mysqli_stmt_execute($stmtDeletePieces);
			mysqli_stmt_close($stmtDeletePieces);
		}

		/* save new game data */
		$insertPieceStmt = mysqli_prepare(
			$dbh,
			"INSERT INTO " . $CFG_TABLE['pieces'] . " (gameID, color, piece, row, col) VALUES (?, ?, ?, ?, ?)"
		);
		$saveGameID = (int)$_SESSION['gameID'];

		/* for each row... */
		for ($i = 0; $i < 8; $i++)
		{
			/* for each col... */
			for ($j = 0; $j < 8; $j++)
			{
				/* if there's a piece at that pos on the board */
				if ($board[$i][$j] != 0)
				{
					/* updated the database */
					if ($board[$i][$j] & BLACK)
						$tmpColor = "black";
					else
						$tmpColor = "white";

					$tmpPiece = getPieceName($board[$i][$j]);
					if ($insertPieceStmt)
					{
						mysqli_stmt_bind_param($insertPieceStmt, "issii", $saveGameID, $tmpColor, $tmpPiece, $i, $j);
						mysqli_stmt_execute($insertPieceStmt);
					}
				}
			}
		}

		if ($insertPieceStmt)
			mysqli_stmt_close($insertPieceStmt);

		/* update lastMove timestamp */
		updateTimestamp();
	}

	function processMessages()
	{
		global $CFG_TABLE, $dbh;
		global $isUndoRequested, $isDrawRequested, $isUndoing, $isGameOver, $isCheckMate, $playersColor, $numMoves, $statusMessage, $CFG_USEEMAILNOTIFICATION;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		if (DEBUG)
			echo("Entering processMessages()<br>\n");

		$isUndoRequested = false;
		$isGameOver = false;

		/* find out which player (black or white) we are serving */
		/* NOTE: When playing in the same computer $playersColor is always the player who logged in first */
		if (DEBUG)
			echo("SharedPC..." . $_SESSION['isSharedPC'] . "<br>\n");
		if ($_SESSION['isSharedPC'])	// Only the player to move is active in this case
			if( ( (($numMoves == -1) || (($numMoves % 2) == 1)) && ($playersColor == "white")) ||
				((($numMoves % 2) == 0) && ($playersColor == "black")) )
				$currentPlayer = $playersColor;
			else						// The player who logged in later is to move
				if($playersColor == "white")
					$currentPlayer = "black";
				else
					$currentPlayer = "white";
		else 							// The players are on different computers
			$currentPlayer = $playersColor;

		if ($currentPlayer == "white")
			$opponentColor = "black";
		else
			$opponentColor = "white";

		if (($currentPlayer !== 'white' && $currentPlayer !== 'black') || ($opponentColor !== 'white' && $opponentColor !== 'black'))
			return;

		$gameId = (int)$_SESSION['gameID'];

		/* *********************************************** */
		/* queue user generated (ie: using forms) messages */
		/* *********************************************** */
		if (DEBUG)
			echo("Processing user generated (ie: form) messages...<br>\n");

		/* queue a request for an undo */
		/* NOTE: only meaningful once at least one move has been made */
		if (isset($_POST['requestUndo']) && $_POST['requestUndo'] == "yes" && $numMoves >= 0)
		{
			/* if the two players are on the same system, execute undo immediately */
			/* NOTE: assumes the two players discussed it live before undoing */
			if ($_SESSION['isSharedPC'])
				$isUndoing = true;
			else
			{
				/* Prevent duplicate undo requests: only queue one if none is already pending */
				$tmpRow = null;
				$stmtUndoCheck = mysqli_prepare($dbh, "SELECT COUNT(*) AS cnt FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgType = 'undo' AND msgStatus = 'request' AND destination = ?");
				if ($stmtUndoCheck)
				{
					mysqli_stmt_bind_param($stmtUndoCheck, "is", $gameId, $opponentColor);
					mysqli_stmt_execute($stmtUndoCheck);
					$tmpCheck = mysqli_stmt_get_result($stmtUndoCheck);
					$tmpRow = $tmpCheck ? mysqli_fetch_assoc($tmpCheck) : null;
					mysqli_stmt_close($stmtUndoCheck);
				}
				if (!$tmpRow || (int)$tmpRow['cnt'] === 0) {
					$stmtUndoInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['messages'] . " (gameID, msgType, msgStatus, destination) VALUES (?, 'undo', 'request', ?)");
					if ($stmtUndoInsert)
					{
						mysqli_stmt_bind_param($stmtUndoInsert, "is", $gameId, $opponentColor);
						mysqli_stmt_execute($stmtUndoInsert);
						mysqli_stmt_close($stmtUndoInsert);
					}
				}
                                // ToDo: Mail an undo request notice to other player??
			}

			updateTimestamp();
		}

		/* queue a request for a draw */
		if (isset($_POST['requestDraw']) && $_POST['requestDraw'] == "yes")
		{
			/* if the two players are on the same system, execute Draw immediately */
			/* NOTE: assumes the two players discussed it live before declaring the game a draw */
			if ($_SESSION['isSharedPC'])
			{
				$stmtDrawShared = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = 'draw', messageFrom = ? WHERE gameID = ?");
				if ($stmtDrawShared)
				{
					mysqli_stmt_bind_param($stmtDrawShared, "si", $currentPlayer, $gameId);
					mysqli_stmt_execute($stmtDrawShared);
					mysqli_stmt_close($stmtDrawShared);
				}
			}
			else
			{
				/* Prevent duplicate draw requests: only queue one if none is already pending */
				$tmpRow = null;
				$stmtDrawCheck = mysqli_prepare($dbh, "SELECT COUNT(*) AS cnt FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgType = 'draw' AND msgStatus = 'request' AND destination = ?");
				if ($stmtDrawCheck)
				{
					mysqli_stmt_bind_param($stmtDrawCheck, "is", $gameId, $opponentColor);
					mysqli_stmt_execute($stmtDrawCheck);
					$tmpCheck = mysqli_stmt_get_result($stmtDrawCheck);
					$tmpRow = $tmpCheck ? mysqli_fetch_assoc($tmpCheck) : null;
					mysqli_stmt_close($stmtDrawCheck);
				}
				if (!$tmpRow || (int)$tmpRow['cnt'] === 0) {
					$stmtDrawInsert = mysqli_prepare($dbh, "INSERT INTO " . $CFG_TABLE['messages'] . " (gameID, msgType, msgStatus, destination) VALUES (?, 'draw', 'request', ?)");
					if ($stmtDrawInsert)
					{
						mysqli_stmt_bind_param($stmtDrawInsert, "is", $gameId, $opponentColor);
						mysqli_stmt_execute($stmtDrawInsert);
						mysqli_stmt_close($stmtDrawInsert);
					}
				}
			}

			updateTimestamp();
		}

		/* response to a request for an undo */
		if (isset($_POST['undoResponse']))
		{
			if (isset($_POST['isUndoResponseDone']) && $_POST['isUndoResponseDone'] == 'yes')
			{
				if ($_POST['undoResponse'] == "yes")
				{
					$tmpStatus = "approved";
					$isUndoing = true;
				}
				else
					$tmpStatus = "denied";

				$stmtUndoResponse = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['messages'] . " SET msgStatus = ?, destination = ? WHERE gameID = ? AND msgType = 'undo' AND msgStatus = 'request' AND destination = ?");
				if ($stmtUndoResponse)
				{
					mysqli_stmt_bind_param($stmtUndoResponse, "ssis", $tmpStatus, $opponentColor, $gameId, $currentPlayer);
					mysqli_stmt_execute($stmtUndoResponse);
					mysqli_stmt_close($stmtUndoResponse);
				}

				updateTimestamp();
			}
		}

		/* response to a request for a draw */
		if (isset($_POST['drawResponse']))
		{
			if (isset($_POST['isDrawResponseDone']) && $_POST['isDrawResponseDone'] == 'yes')
			{
				if ($_POST['drawResponse'] == "yes")
				{
					$tmpStatus = "approved";
					$stmtDrawApprove = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = 'draw', messageFrom = ? WHERE gameID = ?");
					if ($stmtDrawApprove)
					{
						mysqli_stmt_bind_param($stmtDrawApprove, "si", $currentPlayer, $gameId);
						mysqli_stmt_execute($stmtDrawApprove);
						mysqli_stmt_close($stmtDrawApprove);
					}
				}
				else
					$tmpStatus = "denied";

				$stmtDrawResponse = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['messages'] . " SET msgStatus = ?, destination = ? WHERE gameID = ? AND msgType = 'draw' AND msgStatus = 'request' AND destination = ?");
				if ($stmtDrawResponse)
				{
					mysqli_stmt_bind_param($stmtDrawResponse, "ssis", $tmpStatus, $opponentColor, $gameId, $currentPlayer);
					mysqli_stmt_execute($stmtDrawResponse);
					mysqli_stmt_close($stmtDrawResponse);
				}

				updateTimestamp();
			}
		}

		/* resign the game */
		if (isset($_POST['resign']) && $_POST['resign'] == "yes")
		{
			$stmtResign = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = 'playerResigned', messageFrom = ? WHERE gameID = ?");
			if ($stmtResign)
			{
				mysqli_stmt_bind_param($stmtResign, "si", $currentPlayer, $gameId);
				mysqli_stmt_execute($stmtResign);
				mysqli_stmt_close($stmtResign);
			}

			updateTimestamp();

			/* if email notification is activated... */
			if ($CFG_USEEMAILNOTIFICATION && ! $_SESSION['isSharedPC'])
			{
				/* get opponent's player ID */
				$opponentID = 0;
				if ($currentPlayer == 'white')
					$stmtOpponentID = mysqli_prepare($dbh, "SELECT blackPlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
				else
					$stmtOpponentID = mysqli_prepare($dbh, "SELECT whitePlayer FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
				if (isset($stmtOpponentID) && $stmtOpponentID)
				{
					mysqli_stmt_bind_param($stmtOpponentID, "i", $gameId);
					mysqli_stmt_execute($stmtOpponentID);
					$tmpOpponentID = mysqli_stmt_get_result($stmtOpponentID);
					$opponentID = ($tmpOpponentID && ($rowOpponent = mysqli_fetch_row($tmpOpponentID))) ? (int)$rowOpponent[0] : 0;
					mysqli_stmt_close($stmtOpponentID);
				}

				$stmtOpponentEmail = mysqli_prepare($dbh, "SELECT value FROM " . $CFG_TABLE['preferences'] . " WHERE playerID = ? AND preference = 'emailNotification'");
				$tmpOpponentEmail = false;
				if ($stmtOpponentEmail)
				{
					mysqli_stmt_bind_param($stmtOpponentEmail, "i", $opponentID);
					mysqli_stmt_execute($stmtOpponentEmail);
					$tmpOpponentEmail = mysqli_stmt_get_result($stmtOpponentEmail);
					mysqli_stmt_close($stmtOpponentEmail);
				}

				/* if opponent is using email notification... */
				if (mysqli_num_rows($tmpOpponentEmail) > 0)
				{
					$opponentEmail = mysqli_fetch_row($tmpOpponentEmail)[0];
					if ($opponentEmail != '')
					{
						/* notify opponent of resignation via email */
						webchessMail('resignation', $opponentEmail, '', $_SESSION['nick'], $_SESSION['gameID']);
					}
				}
			}
		}


		/* ******************************************* */
		/* process queued messages (ie: from database) */
		/* ******************************************* */
		$tmpMessages = false;
		$stmtQueuedMessages = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND destination = ?");
		if ($stmtQueuedMessages)
		{
			mysqli_stmt_bind_param($stmtQueuedMessages, "is", $gameId, $currentPlayer);
			mysqli_stmt_execute($stmtQueuedMessages);
			$tmpMessages = mysqli_stmt_get_result($stmtQueuedMessages);
			mysqli_stmt_close($stmtQueuedMessages);
		}

		while($tmpMessage = mysqli_fetch_assoc($tmpMessages))
		{
			switch($tmpMessage['msgType'])
			{
				case 'undo':
					switch($tmpMessage['msgStatus'])
					{
						case 'request':
							$isUndoRequested = true;
							break;
						case 'approved':
										$stmtDeleteUndoApproved = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgType = 'undo' AND msgStatus = 'approved' AND destination = ?");
										if ($stmtDeleteUndoApproved)
										{
											mysqli_stmt_bind_param($stmtDeleteUndoApproved, "is", $gameId, $currentPlayer);
											mysqli_stmt_execute($stmtDeleteUndoApproved);
											mysqli_stmt_close($stmtDeleteUndoApproved);
										}
							$statusMessage .= "Undo approved";
							break;
						case 'denied':
							$isUndoing = false;
										$stmtDeleteUndoDenied = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgType = 'undo' AND msgStatus = 'denied' AND destination = ?");
										if ($stmtDeleteUndoDenied)
										{
											mysqli_stmt_bind_param($stmtDeleteUndoDenied, "is", $gameId, $currentPlayer);
											mysqli_stmt_execute($stmtDeleteUndoDenied);
											mysqli_stmt_close($stmtDeleteUndoDenied);
										}
							$statusMessage .= "Undo denied";
							break;
					}
					break;

				case 'draw':
					switch($tmpMessage['msgStatus'])
					{
						case 'request':
							$isDrawRequested = true;
							break;
						case 'approved':
										$stmtDeleteDrawApproved = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgType = 'draw' AND msgStatus = 'approved' AND destination = ?");
										if ($stmtDeleteDrawApproved)
										{
											mysqli_stmt_bind_param($stmtDeleteDrawApproved, "is", $gameId, $currentPlayer);
											mysqli_stmt_execute($stmtDeleteDrawApproved);
											mysqli_stmt_close($stmtDeleteDrawApproved);
										}
							$statusMessage .= "Draw approved";
							break;
						case 'denied':
										$stmtDeleteDrawDenied = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgType = 'draw' AND msgStatus = 'denied' AND destination = ?");
										if ($stmtDeleteDrawDenied)
										{
											mysqli_stmt_bind_param($stmtDeleteDrawDenied, "is", $gameId, $currentPlayer);
											mysqli_stmt_execute($stmtDeleteDrawDenied);
											mysqli_stmt_close($stmtDeleteDrawDenied);
										}
							$statusMessage .= "Draw denied";
							break;
					}
					break;
			}
		}

		/* requests pending */
		$tmpMessages = false;
		$stmtPendingMessages = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['messages'] . " WHERE gameID = ? AND msgStatus = 'request' AND destination = ?");
		if ($stmtPendingMessages)
		{
			mysqli_stmt_bind_param($stmtPendingMessages, "is", $gameId, $opponentColor);
			mysqli_stmt_execute($stmtPendingMessages);
			$tmpMessages = mysqli_stmt_get_result($stmtPendingMessages);
			mysqli_stmt_close($stmtPendingMessages);
		}

		while($tmpMessage = mysqli_fetch_assoc($tmpMessages))
		{
			switch($tmpMessage['msgType'])
			{
				case 'undo':
					$statusMessage .= "Your undo request is pending";
					break;
				case 'draw':
					$statusMessage .= "Your request for a draw is pending";
					break;
			}
		}

		/* game level status: draws, resignations and checkmate */
		/* if checkmate, update games table */
		if (isset($_POST['isCheckMate']) && $_POST['isCheckMate'] == 'true')
		{
			$stmtCheckmate = mysqli_prepare($dbh, "UPDATE " . $CFG_TABLE['games'] . " SET gameMessage = 'checkMate', messageFrom = ? WHERE gameID = ?");
			if ($stmtCheckmate)
			{
				mysqli_stmt_bind_param($stmtCheckmate, "si", $currentPlayer, $gameId);
				mysqli_stmt_execute($stmtCheckmate);
				mysqli_stmt_close($stmtCheckmate);
			}
		}
                        // ToDo: Mail checkmate notification to opponent

		$tmpMessage = null;
		$stmtGameStatus = mysqli_prepare($dbh, "SELECT gameMessage, messageFrom FROM " . $CFG_TABLE['games'] . " WHERE gameID = ?");
		if ($stmtGameStatus)
		{
			mysqli_stmt_bind_param($stmtGameStatus, "i", $gameId);
			mysqli_stmt_execute($stmtGameStatus);
			$tmpMessages = mysqli_stmt_get_result($stmtGameStatus);
			$tmpMessage = $tmpMessages ? mysqli_fetch_assoc($tmpMessages) : null;
			mysqli_stmt_close($stmtGameStatus);
		}

		if (!$tmpMessage)
			return;

		if ($tmpMessage['gameMessage'] == "draw")
		{
			$statusMessage .= "Game ended in a draw";
			$isGameOver = true;
		}

		if ($tmpMessage['gameMessage'] == "playerResigned")
		{
			$statusMessage .= $tmpMessage['messageFrom']." has resigned the game";
			$isGameOver = true;
		}

		if ($tmpMessage['gameMessage'] == "checkMate")
		{
			$statusMessage .= "Checkmate! ".$tmpMessage['messageFrom']." has won the game";
			$isGameOver = true;
			$isCheckMate = true;
		}
	}