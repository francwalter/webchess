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

function MessagePlayer(playerID)
{
    var frame = document.getElementById('messageFrame');
    if (!frame) {
        return;
    }

    var currentTheme = document.body.getAttribute("data-theme")
        || document.documentElement.getAttribute("data-bs-theme")
        || "light";
    frame.src = "sendmessage.php?to=" + encodeURIComponent(playerID) + "&theme=" + encodeURIComponent(currentTheme);

    var modalElement = document.getElementById('messageModal');
    if (!modalElement) {
        return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
}

function HideMessage(messageID)
{
    document.messageHideForm.messageID.value=messageID;
    document.messageHideForm.submit();
}
