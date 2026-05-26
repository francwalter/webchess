// $Id: board.js,v 1.9 2010/08/18 00:32:24 sandking Exp $

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

function getObject(obj) {
    if (!obj) return null;
    if (typeof obj \u003d\u003d \"object\") return obj;
    
    if (document.getElementById) {
        var element \u003d document.getElementById(obj);
        if(element) return element;
        
        var elements \u003d document.getElementsByName(obj);
        if (elements \u0026\u0026 elements.length \u003e 0) return elements[0];
    }
    
    if (document.all) {
        return document.all[obj];
    }
    
    return null;
}

function isGameDrawn()
{
	// Stalemate?
	if(isKingInCheck != \u00271\u0027)
	{	// Not in check
		var myColor \u003d WHITE;
		if(numMoves \u003e\u003d 0 \u0026\u0026 chessHistory[numMoves][CURCOLOR] \u003d\u003d \u0027white\u0027)
			 myColor \u003d BLACK;
		if(countMoves(myColor) \u003d\u003d 0)
		{
			alert(\u0027Stalemate - You should offer your opponent a draw\u0027);
		}
	}

	// Is the game drawn due to insufficient material to checkmate?
	var count \u003d 0;
	var canCheckmate \u003d false;
	for (var i \u003d 0; i \u003c 8; i++)
	{
		for (var j \u003d 0; j \u003c 8; j++)
			if(board[i][j] !\u003d 0 \u0026\u0026 (board[i][j] \u0026 COLOR_MASK) !\u003d KING)
			{
				if((board[i][j] \u0026 COLOR_MASK) !\u003d KNIGHT \u0026\u0026 (board[i][j] \u0026 COLOR_MASK) !\u003d BISHOP)
					canCheckmate \u003d true;
				else
					count++;
			}
	}
	if(count \u003c 2 \u0026\u0026 !canCheckmate)
	{
		alert(\u0027Insufficient material to checkmate - You should offer your opponent a draw\u0027);
	}

	// Is the game drawn because this is the third time that the exact same position arises?
	if(numMoves \u003e\u003d 0 \u0026\u0026 isThirdTimePosDraw(theFEN))
	{
		alert(\u0027Draw (this position has occurred three times) - You should offer your opponent a draw\u0027)
	}

	// Draw because of no capture of pawn move for the last 50 moves?
	if(numMoves \u003e\u003d 0 \u0026\u0026 isFiftyMoveDraw(theFEN[theFEN.length-1]))
	{
		alert(\u0027Draw (50 move rule) - You should offer your opponent a draw\u0027);
	}
}

function displayCaptPieces() {
	var color \u003d \u0027white\u0027;
	var html \u003d \u0027\u003cdiv\u003e\u0027;
	var piece \u003d \u0027\u0027;
	for(var i\u003d0; i \u003c captPieces.length; i++)
	{
		for(var j\u003d0; j \u003c captPieces[i].length; j++)
		{
			piece \u003d color + \u0027_\u0027 + captPieces[i][j];
			html += \u0027\u003cimg src\u003d\"images/\u0027 + CURRENTTHEME + \u0027/\u0027 + piece + \u0027.\u0027 + cfgImageExt + \u0027\" width\u003d\"\u0027;
			html += parseInt(squareSize * 3 / 5) + \u0027\" height\u003d\"\u0027 + parseInt(squareSize * 3 / 5) + \u0027\" alt\u003d\"\u0027 + piece + \u0027\" /\u003e\u0027;
		}
		html += \"\u003c/div\u003e\\n\u003cdiv\u003e\";
		color \u003d \u0027black\u0027;
	}
	html += \u0027\u003c/div\u003e\u0027;
    var capturesObj \u003d getObject(\u0027captures\u0027);
	if (capturesObj) capturesObj.innerHTML \u003d html;
}

function unhighlightCurMove()
{
	var square \u003d chessHistory[numMoves][FROMROW] * 8 + chessHistory[numMoves][FROMCOL];
	var bgCol \u003d \u0027\u0027;
	if(square % 8 \u003c 6)
		bgCol \u003d getObject(\u0027tsq\u0027 + (square + 2) + \u0027\u0027).style.backgroundColor;
	else
		bgCol \u003d getObject(\u0027tsq\u0027 + (square - 2) + \u0027\u0027).style.backgroundColor;

	getObject(\u0027tsq\u0027 + square + \u0027\u0027).style.backgroundColor \u003d bgCol;
	unhighlight(chessHistory[numMoves][TOROW], chessHistory[numMoves][TOCOL]);
}

function highlightCurMove()
{
    var squareObj \u003d getObject(\u0027tsq\u0027 + (chessHistory[numMoves][FROMROW] * 8 + chessHistory[numMoves][FROMCOL]));
	if (squareObj) squareObj.style.backgroundColor \u003d \u0027#FF0\u0027;
	setTimeout(\u0027highlight(chessHistory[numMoves][TOROW], chessHistory[numMoves][TOCOL])\u0027, 200);
	setTimeout(\u0027unhighlightCurMove()\u0027, 750);
}

function drawCoordinates(invertBoard) {
  var i, j;
  for(i \u003d 1; i \u003c 9; i++) {
    if(!invertBoard) {
      j \u003d i;
    } else {
      j \u003d 9 - i;
    }
    var rankObj \u003d getObject(\u0027rank\u0027+i);
    if (rankObj) rankObj.innerHTML \u003d j;
    var fileObj \u003d getObject(\u0027file\u0027+(i-1));
    if (fileObj) fileObj.innerHTML \u003d Files[j-1];
  }
}

function moveTo(objMoveId)
{
	if(currMoveIdx \u003e 0)
	{
        var prevMoveObj \u003d getObject(\u0027m\u0027 + currMoveIdx + \u0027\u0027);
		if (prevMoveObj) prevMoveObj.style.backgroundColor \u003d \u0027#F5F5DC\u0027;
	}
	currMoveIdx \u003d objMoveId.id.slice(1);
	currMoveIdx \u003d parseInt(currMoveIdx);
	FENToBoard(theFEN[currMoveIdx]);
	var theBoardHtml \u003d htmlBoard();
    var boardContainer \u003d getObject(\u0027chessboard\u0027);
	if (boardContainer) boardContainer.innerHTML \u003d theBoardHtml;
	drawCoordinates(perspective \u003d\u003d \u0027black\u0027);
	var currMoveObj \u003d getObject(\u0027m\u0027 + currMoveIdx + \u0027\u0027);
    if (currMoveObj) currMoveObj.style.backgroundColor \u003d \u0027#3C86F6\u0027;
}

function moveJmp(moveDelta)
{
	var moveIdx \u003d currMoveIdx;
	if(moveIdx + moveDelta \u003e theFEN.length - 1)
	{
		moveIdx \u003d theFEN.length - 1;
	}
	else if(moveIdx + moveDelta \u003c 0)
	{
		moveIdx \u003d 0;
	}
	else
	{
		moveIdx += moveDelta;
	}
    var targetMoveObj \u003d getObject(\u0027m\u0027 + moveIdx + \u0027\u0027);
	if (targetMoveObj) moveTo(targetMoveObj);
}

function displayMovesColumns() {
	var objGamebody \u003d getObject(\u0027gamebody\u0027);
    if (!objGamebody) return;

	var theMoves \u003d \u0027\u003cspan id\u003d\"m0\"\u003e\u003c/span\u003e\u0027;
	var moveId \u003d 1;
	theMoves += \u0027\u003cdiv align\u003d\"center\" style\u003d\"padding-top:5px; padding-bottom:5px;\"\u003e\u003ctable cellpadding\u003d\"0\" cellspacing\u003d\"0\" style\u003d\"padding:0; border-collapse: collapse;border-spacing:0;\" width\u003d\"225px\"\u003e\u0027;
	for(var i \u003d 0; i \u003c moves.length; i++)
	{
		if(isGameOver \u003d\u003d \u00271\u0027)
		{
			theMoves += \u0027\u003ctr\u003e\u003ctd class\u003d\"mn\" style\u003d\"border:1px solid #888; text-align:right;\"\u003e\u0027 + (i+1) + \u0027. \u003c/td\u003e\u0027;
			theMoves += \u0027\u003ctd id\u003d\"m\u0027 + (moveId) + \u0027\" class\u003d\"wm\" \u0027 + \u0027onclick\u003d\"moveTo(this);\" style\u003d\"border:1px solid #888;\"\u003e\u0027 + moves[i][0] + \u0027\u003c/td\u003e\u0027;
			theMoves += \u0027\u003ctd id\u003d\"m\u0027 + (moveId+1) + \u0027\" class\u003d\"bm\" \u0027 + \u0027onclick\u003d\"moveTo(this);\" style\u003d\"border:1px solid #888;\"\u003e \u0027 + moves[i][1] + \u0027\u003c/td\u003e\u003c/tr\u003e\u0027;
			moveId \u003d moveId + 2;
		}
		else
		{
			theMoves += \u0027\u003ctr\u003e\u003ctd class\u003d\"mn\" style\u003d\"border:1px solid #888; text-align:right;\"\u003e\u0027 + (i+1) + \u0027.\u003c/td\u003e \u003ctd class\u003d\"wm\" style\u003d\"border:1px solid #888;\"\u003e\u0027;
			theMoves += moves[i][0] + \u0027\u003c/td\u003e\u003ctd style\u003d\"border:1px solid #888;\"\u003e \u0027 + moves[i][1] + \u0027\u003c/td\u003e\u003c/tr\u003e\u0027;
		}
	}
	theMoves += \u0027\u003c/table\u003e\u003c/div\u003e\u0027;
	objGamebody.innerHTML \u003d theMoves;
}

function displayMovesParagraph() {
	var objGamebody \u003d getObject(\u0027gamebody\u0027);
    if (!objGamebody) return;

	var theMoves \u003d \u0027\u003cspan id\u003d\"m0\"\u003e\u003c/span\u003e\u0027;
	var moveId \u003d 1;
	for(var i \u003d 0; i \u003c moves.length; i++)
	{
		if(isGameOver \u003d\u003d \u00271\u0027)
		{
			theMoves += \u0027\u003cspan id\u003d\"m\u0027 + moveId++ + \u0027\" class\u003d\"wm\" \u0027 + \u0027onclick\u003d\"moveTo(this);\"\u003e\u003cspan class\u003d\"mn\"\u003e\u0027 + (i+1) + \u0027.\u003c/span\u003e \u0027 + moves[i][0] + \u0027\u003c/span\u003e\u0027;
			theMoves += \u0027\u003cspan id\u003d\"m\u0027 + moveId++ + \u0027\" class\u003d\"bm\" \u0027 + \u0027onclick\u003d\"moveTo(this);\"\u003e \u0027 + moves[i][1] + \u0027\u003c/span\u003e \u0027;
		}
		else
		{
			theMoves += \u0027\u003cspan class\u003d\"wm\"\u003e\u003cspan class\u003d\"mn\"\u003e\u0027 + (i+1) + \u0027.\u003c/span\u003e \u0027 + moves[i][0] + \u0027\u003c/span\u003e \u0027 + moves[i][1] + \u0027 \u0027;
		}
	}
	objGamebody.innerHTML \u003d theMoves;
}

function displayMoves() {
	if(historyLayout \u003d\u003d \u0027columns\u0027)
		displayMovesColumns();
	else
		displayMovesParagraph();
}

function htmlBoard()
{	// Returns the HTML-code for an empty chessboard (Note: Fixed square size and theme)
	if(isBoardDisabled \u003d\u003d \u0027\u0027)
	{
		var classWSquare \u003d \u0027light_enabled\u0027;
		var classBSquare \u003d \u0027dark_enabled\u0027;
		var classHeader \u003d \u0027header_enabled\u0027;
	}
	else
	{
		var classWSquare \u003d \u0027light_disabled\u0027;
		var classBSquare \u003d \u0027dark_disabled\u0027;
		var classHeader \u003d \u0027header_disabled\u0027;
	}
	var sqBackground \u003d [classWSquare, classBSquare];
	var invertBoard \u003d (perspective \u003d\u003d \u0027black\u0027);
	var borderWidth \u003d squareSize / 2;
	var rank \u003d 8;
	var rankLabel \u003d rank;
	if(invertBoard)
	{
		rankLabel \u003d 1
	}
	var j \u003d 1;

	var theBoardHtml \u003d \u0027\u003ctable id\u003d\"theBoard\" cellpadding\u003d\"0\" style\u003d\"border:1px solid #888; padding:0; border-collapse: collapse;border-spacing:0; margin-bottom:5px; margin-left: auto; margin-right: auto;\"\u003e\u0027;
	theBoardHtml += \u0027\u003ctr id\u003d\"bordertop\" style\u003d\"height:\u0027 + borderWidth + \u0027px;\"\u003e\u003ctd colspan\u003d\"10\" class\u003d\"\u0027 + classHeader + \u0027\"\u003e\u0026nbsp;\u003c/td\u003e\u003c/tr\u003e\u0027;
	theBoardHtml += \u0027\u003ctr\u003e\u003ctd id\u003d\"rank\u0027 + rank-- + \u0027\" class\u003d\"\u0027 + classHeader + \u0027\" width\u003d\"\u0027 + borderWidth + \u0027\"\u003e\u0027 + rankLabel + \u0027\u003c/td\u003e\u0027;
	var row \u003d 0;
	var col \u003d 0;
	for(var k \u003d 63; k \u003e\u003d 0; k--)
	{
		if((k+1) % 8 \u003d\u003d 0)
		{
			var i \u003d k - 7;
			if(invertBoard)
				i \u003d 63 - i;
		}
		else
		{
			if(invertBoard)
				i--;
			else
				i++;
		}
		theBoardHtml += \u0027\u003ctd id\u003d\"tsq\u0027 + i + \u0027\" class\u003d\"\u0027 + sqBackground[j] + \u0027\" width\u003d\"\u0027 + squareSize + \u0027\" height\u003d\"\u0027 + squareSize + \u0027\"\u003e\u0027;
		var piece \u003d \u0027\u0027;
		var source \u003d \u0027\u0027;
		row \u003d parseInt(i / 8);
		col \u003d i % 8;
		if(board[row][col] != 0)
		{
			piece \u003d getPieceColor(board[row][col]) + \u0027_\u0027 + getPieceName(board[row][col]);
			source \u003d \u0027images/\u0027 + CURRENTTHEME + \u0027/\u0027 + piece + \u0027.\u0027 + cfgImageExt;	// Update the square
			theBoardHtml += \u0027\u003cimg alt\u003d\"\u0027 + piece + \u0027\" id\u003d\"sq\u0027 + i + \u0027\" \u0027;
			theBoardHtml += \u0027src\u003d\"\u0027 + source + \u0027\" width\u003d\"\u0027 + squareSize + \u0027\" height\u003d\"\u0027 + squareSize + \u0027\"\u003e\u0027;
		}
		else
		{
			theBoardHtml += \u0027\u0026nbsp;\u0027;
		}
		theBoardHtml += \u0027\u003c/td\u003e\u0027;
		if((k % 8) \u003d\u003d\u003d 0) {
			theBoardHtml += \u0027\u003ctd id\u003d\"rbrd\u0027 + (rank+1) + \u0027\" class\u003d\"\u0027 + classHeader + \u0027\" width\u003d\u0027 + borderWidth + \u0027\"\u003e\u0026nbsp;\u003c/td\u003e\u003c/tr\u003e\u0027;
			if(k != 0) {
				if(invertBoard)
				{
					rankLabel \u003d 9 - rank;
				}
				else
				{
					rankLabel \u003d rank;
				}
				theBoardHtml += \u0027\u003ctr\u003e\u003ctd id\u003d\"rank\u0027 + rank-- + \u0027\" class\u003d\"\u0027 + classHeader + \u0027\" width\u003d\"\u0027 + borderWidth + \u0027\"\u003e\u0027 + rankLabel + \u0027\u003c/td\u003e\u0027;
			}
		}
		else
		{
			j \u003d 1 - j;
		}
	}
	theBoardHtml += \u0027\u003ctr id\u003d\"borderbottom\" class\u003d\"\u0027 + classHeader + \u0027\" height\u003d\"\u0027 + borderWidth + \u0027\"\u003e\u003ctd width\u003d\"\u0027 + borderWidth + \u0027\"\u003e\u0026nbsp;\u003c/td\u003e\u0027;
	var fileLabel;
	for(i \u003d 0; i \u003c 8; i++) {
		if(invertBoard) {
			fileLabel \u003d Files[7-i];
		}
		else
		{
			fileLabel \u003d Files[i];
		}
		theBoardHtml += \u0027\u003ctd id\u003d\"file\u0027 + i + \u0027\" class\u003d\"\u0027 + classHeader + \u0027\"\u003e\u0027 + fileLabel + \u0027\u003c/td\u003e\u0027;
	}
	theBoardHtml += \u0027\u003ctd id\u003d\"rbrd0\" class\u003d\"\u0027 + classHeader + \u0027\"\u003e\u0026nbsp;\u003c/td\u003e\u003c/tr\u003e\u003c/table\u003e\u0027;
	return theBoardHtml;
}

var theFEN \u003d new Array();
var currMoveIdx \u003d 0;

function initChessBoard()
{
    // Initialize theme first
    if (typeof window.initTheme \u003d\u003d\u003d \u0027function\u0027) {
        window.initTheme();
    }

	var invertBoard \u003d (perspective \u003d\u003d \u0027black\u0027);
    var boardContainer \u003d document.getElementById(\u0027chessboard\u0027);
    if (boardContainer) {
	    boardContainer.innerHTML \u003d htmlBoard();
    }

	// Display the current move below the chessboard
	if(moves.length \u003e 0)
	{
		var lastMove \u003d moves.length + \u0027.\u0027;
        var curMoveObj \u003d getObject(\u0027curmove\u0027);
        if (curMoveObj) {
            if(moves[moves.length-1][1] != \u0027\u0027)
            {
                curMoveObj.innerHTML \u003d lastMove + \u0027..\u0027 + moves[moves.length-1][1];
            }
            else
            {
                curMoveObj.innerHTML \u003d lastMove + \u0027 \u0027 + moves[moves.length-1][0];
            }
            if(isGameOver != \u00271\u0027)
                curMoveObj.onclick \u003d function(){highlightCurMove();};
        }
	}
	
    if (getObject(\u0027gameid\u0027)) getObject(\u0027gameid\u0027).innerHTML \u003d \u0027Game #\u0027 + gameId;
	if (getObject(\u0027players\u0027)) getObject(\u0027players\u0027).innerHTML \u003d players;
	if (getObject(\u0027whosmove\u0027)) getObject(\u0027whosmove\u0027).innerHTML \u003d whosMove;
	if (getObject(\u0027checkmsg\u0027)) getObject(\u0027checkmsg\u0027).innerHTML \u003d checkMsg;
	if (getObject(\u0027statusmsg\u0027)) getObject(\u0027statusmsg\u0027).innerHTML \u003d statusMessage;
	
    displayMoves();
	displayCaptPieces();

	theFEN \u003d historyToFEN();

	if (getObject(\"btnMainMenu\")) getObject(\"btnMainMenu\").disabled \u003d false;
	if (getObject(\"btnReload\")) getObject(\"btnReload\").disabled \u003d false;
	if (getObject(\"btnPGN\")) getObject(\"btnPGN\").disabled \u003d false;
	if (getObject(\"btnLogout\")) getObject(\"btnLogout\").disabled \u003d false;

	if (getObject(\"btnMainMenu\")) getObject(\"btnMainMenu\").onclick \u003d function(){displayMainmenu();};
	if (getObject(\"btnReload\")) getObject(\"btnReload\").onclick \u003d function(){reloadPage(this);};
	if (getObject(\"btnPGN\")) getObject(\"btnPGN\").onclick \u003d function(){downloadPGN();};
	if (getObject(\"btnLogout\")) getObject(\"btnLogout\").onclick \u003d function(){logout();};

	if (getObject(\"btnUndo\")) getObject(\"btnUndo\").onclick \u003d function(){undo();};
	if (getObject(\"btnDraw\")) getObject(\"btnDraw\").onclick \u003d function(){draw();};
	if (getObject(\"btnResign\")) getObject(\"btnResign\").onclick \u003d function(){resigngame();};

	if(isBoardDisabled != \u00271\u0027)
	{
		if (getObject(\"btnUndo\")) getObject(\"btnUndo\").disabled \u003d false;
		if (getObject(\"btnDraw\")) getObject(\"btnDraw\").disabled \u003d false;
		if (getObject(\"btnResign\")) getObject(\"btnResign\").disabled \u003d false;
	}
	if(isGameOver \u003d\u003d \u00271\u0027)
	{ // Allow game replay
		if (getObject(\u0027gamebuttons\u0027)) getObject(\u0027gamebuttons\u0027).style.display \u003d \u0027none\u0027;
		currMoveIdx \u003d theFEN.length - 1;
		var navButtons \u003d \u0027\u003cform id\u003d\"navigation\" action\u003d\"\"\u003e\u0027;
		navButtons += \u0027\u003cspan id\u003d\"navbuttons\"\u003e\u0027;
		navButtons += \u0027\u003cinput id\u003d\"start\" title\u003d\"Start of game\" type\u003d\"button\" value\u003d\"Start\" /\u003e\u0027;
		navButtons += \u0027\u003cinput id\u003d\"jmpback\" title\u003d\"Go back five halfmoves\" type\u003d\"button\" value\u003d\"\u0026nbsp;\u003clt;\u003clt;\u0026nbsp;\" /\u003e\u0027;
		navButtons += \u0027\u003cinput id\u003d\"prev\" title\u003d\"Go back one halfmove\" type\u003d\"button\" value\u003d\"\u0026nbsp;\u003clt;\u0026nbsp;\" /\u003e\u0027;
		navButtons += \u0027\u003cinput id\u003d\"next\" title\u003d\"Go forward one halfmove\" type\u003d\"button\" value\u003d\"\u0026nbsp;\u003egt;\u0026nbsp;\" /\u003e\u0027;
		navButtons += \u0027\u003cinput id\u003d\"jmpfwd\" title\u003d\"Go forward five halfmoves\" type\u003d\"button\" value\u003d\"\u0026nbsp;\u003egt;\u003egt;\u0026nbsp;\" /\u003e\u0027;
		navButtons += \u0027\u003cinput id\u003d\"end\" title\u003d\"End of game\" type\u003d\"button\" value\u003d\"End\" /\u003e\u0027;
		navButtons += \u0027\u003c/span\u003e\u0027;
		navButtons += \u0027\u003c/form\u003e\u0027;
		if (getObject(\u0027gamenav\u0027)) getObject(\u0027gamenav\u0027).innerHTML \u003d navButtons;
		
        if (getObject(\"start\")) getObject(\"start\").onclick \u003d function(){moveJmp(-10000);};
		if (getObject(\\"jmpback\")) getObject(\"jmpback\").onclick \u003d function(){moveJmp(-5);};
		if (getObject(\"prev\")) getObject(\"prev\").onclick \u003d function(){moveJmp(-1);};
		if (getObject(\"next\")) getObject(\"next\").onclick \u003d function(){moveJmp(1);};
		if (getObject(\"jmpfwd\")) getObject(\"jmpfwd\").onclick \u003d function(){moveJmp(5);};
		if (getObject(\"end\")) getObject(\"end\").onclick \u003d function(){moveJmp(10000);};
	}
	else
	{ // Alert the players it\u0027s stalemate, 50 move draw or the same position has occurred three times
		isGameDrawn();
	}

	if(isPlayersTurn \u003d\u003d \u00271\u0027)
	{ // No need to set event handlers unless it\u0027s the player\u0027s move
		for(var i\u003d0; i \u003c 64; i++) {
			var sq \u003d getObject(\"tsq\" + i);
            if (sq) sq.onclick \u003d function(){squareClicked(this);};
		}
	}

	if(autoreload \u003e 0)
    	var intervalId \u003d setInterval(function() {
            window.location.replace(\u0027chess.php?autoreload\u003dyes\u0027);
        }, autoreload * 1000);
}

if (document.readyState \u003d\u003d\u003d \u0027loading\u0027) {
    document.addEventListener(\u0027DOMContentLoaded\u0027, initChessBoard);
} else {
    initChessBoard();
}
