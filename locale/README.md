# WebChess locale placeholders

This folder prepares array-based EN/DE GUI translations without changing visible text yet.

## Files

- `en.php`: English map (`source => target`)
- `de.php`: German map (`source => target`)

Both currently return empty arrays on purpose.

## How it works

`lang.php` loads `locale/<gui-language>.php` based on the GUI language preference (`en` or `de`).
If no key is found, the original source string is returned unchanged.

This keeps current behavior stable while enabling incremental translation later.

