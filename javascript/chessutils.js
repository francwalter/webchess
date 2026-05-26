// $Id: chessutils.js,v 1.8 2010/08/18 02:48:19 sandking Exp $

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

 /* these are utility functions used by other functions */
var Files \u003d [\u0027a\u0027, \u0027b\u0027, \u0027c\u0027, \u0027d\u0027, \u0027e\u0027, \u0027f\u0027, \u0027g\u0027, \u0027h\u0027];

var pieceNameToLtr \u003d new Array();
pieceNameToLtr \u003d {\u0027king\u0027:\u0027k\u0027, \u0027queen\u0027:\u0027q\u0027, \u0027rook\u0027:\u0027r\u0027, \u0027bishop\u0027:\u0027b\u0027, \u0027knight\u0027:\u0027n\u0027, \u0027pawn\u0027:\u0027p\u0027};

var pieceLtrToName \u003d new Array();
pieceLtrToName \u003d {\u0027k\u0027:\u0027king\u0027, \u0027q\u0027:\u0027queen\u0027, \u0027r\u0027:\u0027rook\u0027, \u0027b\u0027:\u0027bishop\u0027, \u0027n\u0027:\u0027knight\u0027, \u0027p\u0027:\u0027pawn\u0027};

var pieceColor \u003d new Array();
pieceColor \u003d {\u0027K\u0027:\u0027w\u0027, \u0027Q\u0027:\u0027w\u0027, \u0027R\u0027:\u0027w\u0027, \u0027B\u0027:\u0027w\u0027, \u0027N\u0027:\u0027w\u0027, \u0027P\u0027:\u0027w\u0027,
			  \u0027k\u0027:\u0027b\u0027, \u0027q\u0027:\u0027b\u0027, \u0027r\u0027:\u0027b\u0027, \u0027b\u0027:\u0027b\u0027, \u0027n\u0027:\u0027b\u0027, \u0027p\u0027:\u0027b\u0027};

var colorLtrToName \u003d new Array();
colorLtrToName \u003d {\u0027w\u0027:\u0027white\u0027, \u0027b\u0027:\u0027black\u0027};

function getObject(obj) {
    if (document.getElementById) {
        if (typeof obj \u003d\u003d \"string\") {
            var element \u003d document.getElementById(obj);
            if(element) {
                return element;
            } else {
                var elements \u003d document.getElementsByName(obj);
                if (elements \u0026\u0026 elements.length \u003e 0) return elements[0];
            }
        } else {
            return obj.style;
        }
    }
    return null;
}

function isInBoard(row, col)
{
	if ((row \u003e\u003d 0) \u0026\u0026 (row \u003c\u003d 7) \u0026\u0026 (col \u003e\u003d 0) \u0026\u0026 (col \u003c\u003d 7))
		return true;
	else
		return false;
}

	function getPieceColor(piece)
	{
		if (BLACK \u0026 piece)
			return \"black\";
		else
			return \"white\";
	}

	function getPieceName(piece)
	{
		var pieceName \u003d new Array();
		pieceName[PAWN] \u003d \"pawn\";
		pieceName[ROOK] \u003d \"rook\";
		pieceName[KNIGHT] \u003d \"knight\";
		pieceName[BISHOP] \u003d \"bishop\";
		pieceName[QUEEN] \u003d \"queen\";
		pieceName[KING] \u003d \"king\";

		return pieceName[piece \u0026 COLOR_MASK];
	}

	function getPieceCode(color, piece)
	{
		var code;
		switch(piece)
		{
			case \"pawn\":
				code \u003d PAWN;
				break;
			case \"knight\":
				code \u003d KNIGHT;
				break;
			case \"bishop\":
				code \u003d BISHOP;
				break;
			case \"rook\":
				code \u003d ROOK;
				break;
			case \"queen\":
				code \u003d QUEEN;
				break;
			case \"king\":
				code \u003d KING;
				break;
		}

		if (color \u003d\u003d \"black\")
			code \u003d BLACK | code;

		return code;
	}

	var tmpOriginalClassName \u003d \"\";

	function highlight(row, col)
	{
		if (board[parseInt(row)][parseInt(col)] !\u003d \"\")
		{
			var square \u003d parseInt(row) * 8 + parseInt(col);
			var element \u003d document.getElementById(\"tsq\" + square);
            if (element) {
                tmpOriginalClassName \u003d element.className;
                element.className \u003d \"highlighted\";
            }
		}

		return true;
	}

	function unhighlight(row, col)
	{
		if (DEBUG)
			alert(\"unhighlight -\u003e row \u003d \" + row + \", col \u003d \" + col);


		if (board[parseInt(row)][parseInt(col)] !\u003d \"\")
		{
			var square \u003d parseInt(row) * 8 + parseInt(col);
			var element \u003d document.getElementById(\"tsq\" + square);
            if (element) {
                element.className \u003d tmpOriginalClassName;
            }
		}

		return true;
	}

	function getOtherColor(color)
	{
		if (color \u003d\u003d \"white\")
			return \"black\";
		else
			return \"white\";
	}

//
// FEN functions
//

function ExpandFEN(FEN) {
  var ones \u003d new Array (\u0027\u0027, \u00271\u0027 ,\u002711\u0027, \u0027111\u0027, \u00271111\u0027, \u002711111\u0027, \u0027111111\u0027, \u00271111111\u0027, \u002711111111\u0027);
  var theFEN \u003d \u0027\u0027;
  for(var i\u003d0; i \u003c FEN.length; i++) {
    if(FEN.charAt(i) \u003e \u00271\u0027 \u0026\u0026 FEN.charAt(i) \u003c \u00279\u0027) {
      theFEN += (ones[Number(FEN.charAt(i))]);
    } else {
      theFEN \u003d theFEN + \u0027\u0027 +  FEN.charAt(i);
    }
  }
  return theFEN.replace(/\\//g, \"\");                     // Leave only pieces and empty squares
}

function SetSquare(Square, Piece) {
  var rank \u003d 7 - parseInt(Square / 8);
  var file \u003d Square % 8;
  var s;
  if(Piece \u003d\u003d \u00271\u0027) {
    s \u003d 0;
  } else {
    s \u003d getPieceCode(colorLtrToName[pieceColor[Piece]], pieceLtrToName[Piece.toLowerCase()]);
  }
  board[rank][file] = s;
}

function FENToBoard(FEN) {
  var FENItems \u003d new Array();
  FENItems \u003d FEN.split(\u0027 \u0027);
  var ExpFEN \u003d ExpandFEN(FENItems[0]);
  var c;
  for(var i\u003d0; i \u003c 64; i++) {
    c \u003d ExpFEN.charAt(i);
    SetSquare(i, c);
  }
  curColor \u003d colorLtrToName[FENItems[1]];
};

function PackFEN(piecePlacement, activeColor, castlingAvail, epSquare, halfmoveClock, fullmoveNumber)
{ // Pack all the FEN fields into one string
	var FEN \u003d \u0027\u0027;
	var idx \u003d 0;
	var empty \u003d 0;
	var c \u003d \u0027\u0027;
	for(var i\u003d0; i \u003c 64; i++)
	{ // Generate the correct piece placement string
		if(i \u003e 0 \u0026\u0026 (i % 8 \u003d\u003d 0))
		{ // New row
			if(empty \u003e 0)
			{ // Count of empty squares does not continue across rows
				FEN += empty + \"\";
				empty \u003d 0;
				idx++;
			}
			FEN += \u0027/\u0027;	// New row
		}
		c \u003d piecePlacement.charAt(i);
		if(c \u003d\u003d \u00271\u0027)
		{ // Count consecutive empty squares
			empty++;
		}
		else
		{ // Non-empty square
			if(empty \u003e 0)
			{ // Add the number of consecutive empty squares to the output string
				FEN += empty + \"\";
				empty \u003d 0;
				idx++;
			}
			FEN += c + \"\";
			idx++;
		}
	}
	if(empty \u003e 0)
	{
		FEN += empty + \"\";
	}
	return FEN + \u0027 \u0027 + activeColor + \u0027 \u0027 + castlingAvail + \u0027 \u0027 + epSquare + \u0027 \u0027 + halfmoveClock + \u0027 \u0027 + fullmoveNumber;
}

function getFENStartPos()
{
	return \u0027rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1\u0027;
}

// Returns an array of FEN strings for the current game
// Note that this function assumes that the game started from the normal initial position
function historyToFEN()
{
	var FEN \u003d new Array();
	FEN[0] \u003d getFENStartPos();	// The start position
	var activeColor \u003d \u0027w\u0027;
	var wKS \u003d \u0027K\u0027;	// Castling availability
	var wQS \u003d \u0027Q\u0027;
	var bKS \u003d \u0027k\u0027;
	var bQS \u003d \u0027q\u0027;
	var castlingAvail \u003d \u0027KQkq\u0027;
	var epSquare \u003d \u0027-\u0027;	// The en passant square
	var halfmoveClock \u003d 0;	// Number of half moves since last capture or pawn move
	var fullmoveNumber \u003d 1;	// The move number
	var piece \u003d \u0027\u0027;
	for (var i \u003d 0; i \u003c\u003d numMoves; i++)
	{
		FEN[i+1] \u003d ExpandFEN(FEN[i]).slice(0, 64);	// Get the piece placement from the FEN string
		if(chessHistory[i][CURCOLOR] \u003d\u003d \u0027white\u0027)
			activeColor \u003d \u0027b\u0027;
		else
		{
			activeColor \u003d \u0027w\u0027;
			fullmoveNumber++;
		}
		var fromCol \u003d chessHistory[i][FROMCOL];
		var fromRow \u003d chessHistory[i][FROMROW];
		var row \u003d chessHistory[i][TOROW];
		var col \u003d chessHistory[i][TOCOL];
		if(FEN[i+1].charAt(col + (7 - row) * 8) !\u003d \u00271\u0027 || chessHistory[i][CURPIECE] \u003d\u003d \u0027pawn\u0027)
			halfmoveClock \u003d 0;	// Restart the count after pawn move or capture
		else
			halfmoveClock++;
		if(typeof chessHistory[i][PROMOTEDTO] \u003d\u003d \"undefined\")
			piece \u003d FEN[i+1].charAt((7 - fromRow) * 8 + fromCol);
		else
		{
			piece \u003d pieceNameToLtr[chessHistory[i][PROMOTEDTO]];
			if(chessHistory[i][CURCOLOR] \u003d\u003d \u0027white\u0027)
				piece \u003d piece.toUpperCase();
		}
		FEN[i+1] \u003d FEN[i+1].slice(0, (7 - fromRow) * 8 + fromCol) + \u00271\u0027 + FEN[i+1].slice((7 - fromRow) * 8 + fromCol + 1);
		FEN[i+1] \u003d FEN[i+1].slice(0, (7 - row) * 8 + col) + piece + FEN[i+1].slice((7 - row) * 8 + col + 1);

		if (chessHistory[i][CURPIECE] \u003d\u003d \u0027king\u0027)
		{ // Can\u0027t castle after the king has been moved
			if(chessHistory[i][CURCOLOR] \u003d\u003d \u0027white\u0027)
			{
				wKS \u003d \u0027\u0027;
				wQS \u003d \u0027\u0027;
			}
			else
			{
				bKS \u003d \u0027\u0027;
				bQS \u003d \u0027\u0027;
			}
			/* if this is a castling move the rook must also be moved */
			if (Math.abs(col - fromCol) \u003d\u003d 2)
			{	// The king only moves two squares when castling
				var rookCol \u003d 0;
				var rookToCol \u003d 3
				if (col - fromCol \u003d\u003d 2)
				{	// Kingside castling (would be \u003d\u003d -2 if queenside)
					rookCol \u003d 7;
					rookToCol \u003d 5;
				}
				FEN[i+1] \u003d FEN[i+1].slice(0, (7 - row) * 8 + rookToCol) + FEN[i+1].charAt((7 - row) * 8 + rookCol) + FEN[i+1].slice((7 - row) * 8 + rookToCol + 1);
				FEN[i+1] \u003d FEN[i+1].slice(0, (7 - row) * 8 + rookCol) + \u00271\u0027 + FEN[i+1].slice((7 - row) * 8 + rookCol + 1);
			}
		}
		else if (chessHistory[i][CURPIECE] \u003d\u003d \u0027rook\u0027)
		{
			if(chessHistory[i][CURCOLOR] \u003d\u003d \u0027white\u0027)
			{
				if(fromRow \u003d\u003d 0)
				{
					if(fromCol \u003d\u003d 0)
						wQS \u003d \u0027\u0027;
					else
						wKS \u003d \u0027\u0027;
				}
			}
			else
			{
				if(fromRow \u003d\u003d 7)
				{
					if(fromCol \u003d\u003d 0)
						bQS \u003d \u0027\u0027;
					else
						bKS \u003d \u0027\u0027;
				}
			}
		}
		else if(chessHistory[i][CURPIECE] \u003d\u003d \u0027pawn\u0027 \u0026\u0026 Math.abs(chessHistory[i][TOROW] - chessHistory[i][FROMROW]) \u003d\u003d 2)
		{ // Pawn double advance, so en passant capture may be possible on the next move
			if(chessHistory[i][CURCOLOR] \u003d\u003d \u0027white\u0027)
			{
				epSquare \u003d Files[fromCol] + \u00273\u0027;
			}
			else
			{
				epSquare \u003d Files[fromCol] + \u00276\u0027;
			}
		}
		castlingAvail \u003d wKS + wQS + bKS + bQS;
		if(castlingAvail \u003d\u003d \u0027\u0027)
			castlingAvail \u003d \u0027-\u0027;
		FEN[i+1] \u003d PackFEN(FEN[i+1], activeColor, castlingAvail, epSquare, halfmoveClock, fullmoveNumber);
		epSquare \u003d \u0027-\u0027;
	}
	return FEN;
}
