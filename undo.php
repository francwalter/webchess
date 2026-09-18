<?php
// $Id: undo.php,v 1.5 2010/08/14 16:57:54 sandking Exp $

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

/* these functions deal specifically with undoing a move */
	function doUndo()
	{
		global $CFG_TABLE, $dbh;
		global $board, $numMoves;

		/* old PHP versions don't have _POST, _GET and _SESSION as auto_globals */
		if (!minimum_version("4.1.0"))
			global $_POST, $_GET, $_SESSION;

		if (!webchessPlayerOwnsGame($dbh, (int)$_SESSION['gameID'], (int)$_SESSION['playerID']))
			return;

		/* get the last move from the history */
		/* NOTE: MySQL currently has no support for subqueries */
		$gameID = (int)$_SESSION['gameID'];
		$stmtMaxTime = mysqli_prepare($dbh, "SELECT MAX(timeOfMove) FROM " . $CFG_TABLE['history'] . " WHERE gameID = ?");
		if (!$stmtMaxTime)
			return;
		mysqli_stmt_bind_param($stmtMaxTime, "i", $gameID);
		mysqli_stmt_execute($stmtMaxTime);
		$tmpMaxTime = mysqli_stmt_get_result($stmtMaxTime);
		$maxTimeRow = $tmpMaxTime ? mysqli_fetch_row($tmpMaxTime) : null;
		$maxTime = $maxTimeRow ? $maxTimeRow[0] : null;
		mysqli_stmt_close($stmtMaxTime);

		if ($maxTime === null)
			return;

		$stmtLastMove = mysqli_prepare($dbh, "SELECT * FROM " . $CFG_TABLE['history'] . " WHERE gameID = ? AND timeOfMove = ? LIMIT 1");
		if (!$stmtLastMove)
			return;
		mysqli_stmt_bind_param($stmtLastMove, "is", $gameID, $maxTime);
		mysqli_stmt_execute($stmtLastMove);
		$moves = mysqli_stmt_get_result($stmtLastMove);

		/* if there actually is a move... */
		if ($moves && ($lastMove = mysqli_fetch_assoc($moves)))
		{
			/* if the last move was played by this player */

				/* undo move */
				$fromRow = $lastMove['fromRow'];
				$fromCol = $lastMove['fromCol'];
				$toRow = $lastMove['toRow'];
				$toCol = $lastMove['toCol'];

				$board[$fromRow][$fromCol] = getPieceCode($lastMove['curColor'], $lastMove['curPiece']);
				$board[$toRow][$toCol] = 0;

				/* check for en-passant */
				/* if pawn moves diagonally without replacing a piece, it's en passant */
				if (($lastMove['curPiece'] == "pawn") && ($toCol != $fromCol) && is_null($lastMove['replaced']))
				{
					if ($lastMove['curColor'] == "black")
						$board[$fromRow][$toCol] = getPieceCode("white", "pawn");
					else
						$board[$fromRow][$toCol] = getPieceCode("black", "pawn");
				}

				/* check for castling */
				if ((($board[$fromRow][$fromCol] & COLOR_MASK) == KING) && (abs($toCol - $fromCol) == 2))
				{
					/* move rook back as well */
					if (($toCol - $fromCol) == 2)
					{
						$board[$fromRow][7] = $board[$fromRow][5];
						$board[$fromRow][5] = 0;
					}
					else
					{
						$board[$fromRow][0] = $board[$fromRow][3];
						$board[$fromRow][3] = 0;
					}
				}

				/* restore lost piece */
				if (!is_null($lastMove['replaced']))
				{
					if ($lastMove['curColor'] == "black")
						$board[$toRow][$toCol] = getPieceCode("white", $lastMove['replaced']);
					else
						$board[$toRow][$toCol] = getPieceCode("black", $lastMove['replaced']);
				}

				/* remove last move from history */
				$numMoves--;
				$stmtDeleteMove = mysqli_prepare($dbh, "DELETE FROM " . $CFG_TABLE['history'] . " WHERE gameID = ? AND timeOfMove = ?");
				if ($stmtDeleteMove)
				{
					mysqli_stmt_bind_param($stmtDeleteMove, "is", $gameID, $maxTime);
					mysqli_stmt_execute($stmtDeleteMove);
					mysqli_stmt_close($stmtDeleteMove);
				}

			/* else */
				/* output error message */
		}

		mysqli_stmt_close($stmtLastMove);
	}
