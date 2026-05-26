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

function getObject(obj) {
    if (document.getElementById) {
        if (typeof obj == "string") {
            var element = document.getElementById(obj);
            if(element) {
                return element;
            } else {
                var elements = document.getElementsByName(obj);
                if (elements && elements.length > 0) return elements[0];
            }
        } else {
            return obj.style;
        }
    }
    return null;
}

function showSection(hash) {
    // Default to continuegame if no hash or invalid hash
    if (!hash || hash === '#' || hash === '') hash = '#continuegame';
    
    console.log("Showing section:", hash);

    // Hide all sections first
    var sections = document.querySelectorAll('.section-content');
    sections.forEach(function(section) {
        section.style.display = 'none';
    });

    // Show the requested section
    var id = hash.substring(1);
    var target = document.getElementById(id);
    
    if (target) {
        target.style.display = 'block';
    } else {
        // Fallback to default if target not found
        var defaultTarget = document.getElementById('continuegame');
        if (defaultTarget) defaultTarget.style.display = 'block';
    }

    // Update active state in navbar
    var navLinks = document.querySelectorAll('#navlist .nav-link');
    navLinks.forEach(function(link) {
        // Use getAttribute to get the exact string as in HTML
        var linkHref = link.getAttribute('href');
        if (linkHref === hash) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

function initMenu() {
    var navLinks = document.querySelectorAll('#navlist .nav-link');
    navLinks.forEach(function(link) {
        var href = link.getAttribute('href');
        // Only attach logic to internal hash links (exclude reload and logout which have onclick handlers)
        if (href && href.startsWith('#') && href !== '#' && !link.getAttribute('onclick')) {
            link.onclick = function(e) {
                e.preventDefault();
                var targetHash = this.getAttribute('href');
                showSection(targetHash);
                window.location.hash = targetHash;
            }
        }
    });

    // Initial load
    showSection(window.location.hash);
}

// Handle browser navigation
window.onhashchange = function() {
    showSection(window.location.hash);
};

// Start initialization when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMenu);
} else {
    initMenu();
}

function reload() {
    window.location.reload();
}

function logout() {
    document.logOutForm.submit();
}
