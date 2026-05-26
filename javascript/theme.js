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

(function() {
    // Theme storage and management
    var currentTheme = null;

    // Get the stored theme from localStorage or system preference
    function getStoredTheme() {
        return localStorage.getItem('webchess-theme');
    }

    function setStoredTheme(theme) {
        localStorage.setItem('webchess-theme', theme);
    }

    function getPreferredTheme() {
        const storedTheme = getStoredTheme();
        if (storedTheme) {
            return storedTheme;
        }
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    function applyTheme(theme, skipStorage) {
        if (theme === currentTheme && document.documentElement.getAttribute('data-bs-theme') === theme) {
            return; 
        }
        
        const root = document.documentElement;
        
        if (theme === 'dark') {
            root.setAttribute('data-bs-theme', 'dark');
            document.body.classList.add('dark-theme');
            document.body.classList.remove('light-theme');
        } else {
            root.setAttribute('data-bs-theme', 'light');
            document.body.classList.remove('dark-theme');
            document.body.classList.add('light-theme');
        }
        
        currentTheme = theme;
        
        if (!skipStorage) {
            setStoredTheme(theme);
        }
        updateThemeButton();
    }

    function setTheme(theme) {
        applyTheme(theme);
    }

    function toggleTheme() {
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
        return false; 
    }

    function updateThemeButton() {
        const themeBtn = document.getElementById('theme-toggle-btn');
        if (themeBtn) {
            if (currentTheme === 'dark') {
                themeBtn.innerHTML = '☀️';
                themeBtn.title = 'Switch to Light Mode';
            } else {
                themeBtn.innerHTML = '🌙';
                themeBtn.title = 'Switch to Dark Mode';
            }
        }
    }

    // Initialize theme on page load
    function initTheme() {
        const preferredTheme = getPreferredTheme();
        applyTheme(preferredTheme, true);
    }

    // Expose toggleTheme and initTheme to global scope
    window.toggleTheme = toggleTheme;
    window.initTheme = initTheme;

    // Reapply theme on visibility change
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            initTheme();
        }
    });
})();
