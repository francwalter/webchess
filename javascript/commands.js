// $Id: commands.js,v 1.4 2010/08/14 16:57:54 sandking Exp $

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


// these functions interact with the server
	function disableButtons()
	{
        var btnUndo = getObject("btnUndo");
        var btnDraw = getObject("btnDraw");
        var btnResign = getObject("btnResign");

        if (btnUndo) btnUndo.disabled = true;
        if (btnDraw) btnDraw.disabled = true;
        if (btnResign) btnResign.disabled = true;
	}

		function undo()
	{
		/* An undo is only meaningful once at least one move has been played */
		if (typeof numMoves === 'undefined' || numMoves < 0)
		{
			showRedWarning(
				(typeof window.undoWarningText !== 'undefined')
					? window.undoWarningText
					: "No move can be undone yet."
			);
			return;
		}

		/* Security confirmation to avoid accidental clicks (Bootstrap modal) */
		var msg = typeof window.confirmUndoText !== 'undefined' ? window.confirmUndoText : "Are you sure you want to request an undo?";
		showConfirmModal(msg, function() {
			disableButtons();
			document.gamedata.requestUndo.value = "yes";
			if (DEBUG)
				alert("gamedata.requestUndo = " + document.gamedata.requestUndo.value);

			document.gamedata.submit();
		});
	}

		function draw()
	{
		/* Security confirmation to avoid accidental clicks (Bootstrap modal) */
		var msg = typeof window.confirmDrawText !== 'undefined' ? window.confirmDrawText : "Are you sure you want to offer a draw?";
		showConfirmModal(msg, function() {
			disableButtons();
			document.gamedata.requestDraw.value = "yes";
			if (DEBUG)
				alert("gamedata.requestDraw = " + document.gamedata.requestDraw.value);

			document.gamedata.submit();
		});
	}

		function resigngame()
	{
		/* Security confirmation to avoid accidental clicks (Bootstrap modal) */
		var msg = typeof window.confirmResignText !== 'undefined' ? window.confirmResignText : "Are you sure you want to resign?";
		showConfirmModal(msg, function() {
			disableButtons();
			document.gamedata.resign.value = "yes";
			if (DEBUG)
				alert("gamedata.resign = " + document.gamedata.resign.value);

			document.gamedata.submit();
		});
	}

		function showRedWarning(message)
	{
		var el = document.getElementById('undoWarning');
		if (el)
		{
			el.textContent = message || '';
			el.classList.remove('d-none');
			el.scrollIntoView({ behavior: 'smooth', block: 'center' });
			/* Automatically hide after a few seconds */
			setTimeout(function(){ el.classList.add('d-none'); }, 6000);
		}
		else
		{
			alert(message);
		}
	}
		function showConfirmModal(message, onConfirm)
	{
		window.__pendingConfirm = onConfirm;
		var textEl = document.getElementById('confirmModalText');
		if (textEl) textEl.textContent = message || '';
		var titleEl = document.getElementById('confirmModalLabel');
		if (titleEl && typeof window.confirmTitleText !== 'undefined')
			titleEl.textContent = window.confirmTitleText;
		var yesBtn = document.getElementById('confirmModalYesBtn');
		if (yesBtn && typeof window.confirmYesText !== 'undefined')
			yesBtn.textContent = window.confirmYesText;
		var noBtn = document.getElementById('confirmModalNoBtn');
		if (noBtn && typeof window.confirmNoText !== 'undefined')
			noBtn.textContent = window.confirmNoText;

		var el = document.getElementById('confirmModal');
		if (el && window.bootstrap && bootstrap.Modal)
		{
			var modal = bootstrap.Modal.getOrCreateInstance(el);
			modal.show();
		}
		else if (window.__pendingConfirm)
		{
			/* Fallback if Bootstrap is unavailable */
			var cb = window.__pendingConfirm;
			window.__pendingConfirm = null;
			cb();
		}
	}

	function confirmModalYes()
	{
		var cb = window.__pendingConfirm;
		window.__pendingConfirm = null;
		var el = document.getElementById('confirmModal');
		if (el && window.bootstrap && bootstrap.Modal)
			bootstrap.Modal.getOrCreateInstance(el).hide();
		if (cb) cb();
	}

	function confirmModalNo()
	{
		window.__pendingConfirm = null;
		var el = document.getElementById('confirmModal');
		if (el && window.bootstrap && bootstrap.Modal)
			bootstrap.Modal.getOrCreateInstance(el).hide();
	}
function displayMainmenu()
	{
        var btnMainMenu = getObject("btnMainMenu");
		if (btnMainMenu) btnMainMenu.disabled = true;
		disableButtons();
		window.open('mainmenu.php', '_self');
	}

	function reloadPage(btnReload)
	{
		if (btnReload) btnReload.disabled = true;
		disableButtons();
		window.open('chess.php', '_self');
	}

	function downloadPGN()
	{
		window.open('openpgn.php', '_self')
	}

	function logout()
	{
		document.gamemenu.action = "mainmenu.php";
		document.gamemenu.submit();
	}

	function promotepawn()
	{
		var blackPawnFound = false;
		var whitePawnFound = false;
		var i = -1;
		while (!blackPawnFound && !whitePawnFound && i < 8)
		{
			i++;

			/* check for black pawn being promoted */
			if (board[0][i] == (BLACK | PAWN))
				blackPawnFound = true;

			/* check for white pawn being promoted */
			if (board[7][i] == (WHITE | PAWN))
				whitePawnFound = true;
		}

		/* to which piece is the pawn being promoted to? */
		var promotedTo = 0;
		for (var j = 0; j <= 3; j++)
		{
			if (document.gamedata.promotion[j].checked)
				promotedTo = parseInt(document.gamedata.promotion[j].value);
		}

		/* change pawn to promoted piece */
		var ennemyColor = "black";
		if (blackPawnFound)
		{
			ennemyColor = "white";
			board[0][i] = (BLACK | promotedTo);

			if (DEBUG)
				alert("Promoting to: (black) " + board[0][i]);

		}
		else if (whitePawnFound)
		{
			board[7][i] = (WHITE | promotedTo);

			if (DEBUG)
				alert("Promoting to: (white) " + board[7][i]);
		}
		else
			alert("WARNING!: cannot find pawn being promoted!");

		/* verify check and checkmate status */
		if (isInCheck(ennemyColor))
		{
			if (DEBUG)
				alert("Promotion results in check!");

			document.gamedata.isInCheck.value = "true";
			document.gamedata.isCheckMate.value = isCheckMate(ennemyColor);
		}
		else
			document.gamedata.isInCheck.value = "false";
		document.gamedata.fromRow.value = chessHistory[numMoves][FROMROW];
		document.gamedata.fromCol.value = chessHistory[numMoves][FROMCOL];
		document.gamedata.toRow.value = chessHistory[numMoves][TOROW];
		document.gamedata.toCol.value = chessHistory[numMoves][TOCOL];

		/* update board and database */
		document.gamedata.submit();
	}
