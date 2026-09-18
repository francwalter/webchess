# WebChess

A fork of the original WebChess project by Jonathan Evraire and Rodrigo Flores from 2004, on [SourceForge](https://sourceforge.net/projects/webchess/), extended and modernized with GitHub Copilot.

WebChess is a browser-based chess application without chess engine.

## Features

- Play chess in the browser, human vs. human (no chess engine included)
- Original WebChess functionality, extended and maintained
- Updated codebase for modern use

## Installation

Please refer to the installation instructions in [docs/INSTALL.txt](docs/INSTALL.txt).

## Admin Helper (Login Throttle)

WebChess includes a CLI-only helper to inspect or clear the server-side login throttle store.

```powershell
php .\admin_login_throttle.php status
php .\admin_login_throttle.php clear
```

- `status` shows whether the throttle file exists and how many entries it contains.
- `clear` deletes the throttle file and resets all current login lockouts.
- This script is CLI-only and returns `403 Forbidden` if accessed via web.

## License

This project is licensed under the GNU General Public License v3.0. See [docs/COPYING.txt](docs/COPYING.txt) for details.

## Credits

Original project by Jonathan Evraire and Rodrigo Flores on SourceForge.