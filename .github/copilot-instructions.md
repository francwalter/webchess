# WebChess Copilot Instructions

## Project context
- This repository is the WebChess PHP application.
- The target runtime is PHP 8.5+.
- Prefer modern PHP syntax and avoid legacy functionality.

## Required constraints
- Do not introduce deprecated or removed PHP features such as `mysql_*`, `ereg()`, `each()`, `create_function()`, `split()`, or short PHP tags.
- Use MySQLi for database access unless a clear, broader refactor is already in progress.
- Keep compatibility with the existing project structure and legacy UI patterns unless explicitly asked to modernize the UI.
- Prefer minimal, targeted changes that preserve behavior.

## Project-specific guidance
- Main application logic is spread across:
  - [connectdb.php](connectdb.php)
  - [chessdb.php](chessdb.php)
  - [chessutils.php](chessutils.php)
  - [mainmenu.php](mainmenu.php)
  - [move.php](move.php)
  - [gui.php](gui.php)
- When changing gameplay logic, check both the database layer and the UI layer.
- When changing database access, verify the connection and query handling in [connectdb.php](connectdb.php) and the relevant DB file.
- Preserve existing session handling and configuration behavior unless the task explicitly requires a change.

## Change approach
- Prefer small, verifiable edits.
- After changing PHP code, run syntax checks with `php -l` on the affected files.
- If the change affects the app entry points, verify the response with a local HTTP check such as `curl -I http://127.0.0.1:8000/index.php`.

## Style guidance
- Keep code readable and conservative.
- Avoid unnecessary refactoring.
- Do not add new dependencies unless requested.
- Preserve existing naming conventions where possible.
