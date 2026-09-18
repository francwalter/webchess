<?php
/*
 * WebChess admin helper: inspect or clear login throttle storage.
 * Usage (CLI only):
 *   php admin_login_throttle.php status
 *   php admin_login_throttle.php clear
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

function webchessThrottlePathCandidates()
{
    $candidates = array();

    $tmpDir = sys_get_temp_dir();
    if (is_string($tmpDir) && $tmpDir !== '') {
        $candidates[] = rtrim($tmpDir, "\\/") . DIRECTORY_SEPARATOR . 'webchess' . DIRECTORY_SEPARATOR . 'login_throttle.json';
    }

    // Fallback path used by mainmenu.php when sys_get_temp_dir() is not usable.
    $candidates[] = rtrim(__DIR__, "\\/") . DIRECTORY_SEPARATOR . 'webchess' . DIRECTORY_SEPARATOR . 'login_throttle.json';

    return array_values(array_unique($candidates));
}

function webchessReadThrottleEntries($filePath)
{
    if (!is_file($filePath)) {
        return array();
    }

    $raw = @file_get_contents($filePath);
    if (!is_string($raw) || $raw === '') {
        return array();
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
}

$action = isset($argv[1]) ? strtolower((string)$argv[1]) : 'status';
$candidates = webchessThrottlePathCandidates();

if ($action === 'status') {
    $foundAny = false;
    foreach ($candidates as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        $foundAny = true;
        $entries = webchessReadThrottleEntries($filePath);
        echo "Found: " . $filePath . PHP_EOL;
        echo "Entries: " . count($entries) . PHP_EOL;
    }

    if (!$foundAny) {
        echo "No throttle file found." . PHP_EOL;
    }
    exit(0);
}

if ($action === 'clear') {
    $cleared = false;
    foreach ($candidates as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        if (@unlink($filePath)) {
            $cleared = true;
            echo "Deleted: " . $filePath . PHP_EOL;
        } else {
            echo "Could not delete: " . $filePath . PHP_EOL;
            exit(1);
        }
    }

    if (!$cleared) {
        echo "No throttle file found to clear." . PHP_EOL;
    }
    exit(0);
}

echo "Unknown action: " . $action . PHP_EOL;
echo "Use: status | clear" . PHP_EOL;
exit(1);

