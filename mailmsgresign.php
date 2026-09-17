<?php
	// $Id: mailmsgresign.php,v 1.5 2010/08/14 16:57:54 sandking Exp $

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

	$mailsubject = "WebChess: ".$opponent." Aufgabe - Spiel: ".$gameID.".";
	$mailmsg = "Dein Gegner ".$opponent." hat aufgegeben im Spiel: ".$gameID.".";
	$mailmsg .= "\n\nDiese Nachricht wurde automatisch versendet und sollte nicht beantwortet werden.\n";
	$mailmsg .= "Zum Spielen bitte auf: " . $CFG_MAINPAGE . " gehen.\n";
