<?php
declare(strict_types=1);

if (!defined('WEBCHESS_VERSION')) {
    define('WEBCHESS_VERSION', '2.1.0');
}

if (!function_exists('webchessGetLatestProjectDateString')) {
    function webchessGetLatestProjectDateString(string $projectRoot): string
    {
        $latestTs = @filemtime(__FILE__);
        if ($latestTs === false) {
            $latestTs = time();
        }

        try {
            $dirIter = new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS);
            $iter = new RecursiveIteratorIterator($dirIter);
            foreach ($iter as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
                    continue;
                }

                $mtime = $fileInfo->getMTime();
                if ($mtime > $latestTs) {
                    $latestTs = $mtime;
                }
            }
        } catch (Exception $e) {
            // Fallback: keep current timestamp
        }

        return date('F j, Y', $latestTs);
    }
}

if (!function_exists('webchessRenderFooter')) {
    function webchessRenderFooter(
        ?string $versionText = null,
        ?string $updatedText = null,
        ?string $repoUrl = null,
        string $wrapperClass = 'footer ctr small'
    ): void {
        if ($versionText === null) {
            $versionText = WEBCHESS_VERSION;
        }

        if ($updatedText === null) {
            $updatedText = webchessGetLatestProjectDateString(__DIR__);
        }

        if ($repoUrl === null) {
            $repoUrl = 'https://github.com/francwalter/webchess/';
        }

        $safeVersion = htmlspecialchars($versionText, ENT_QUOTES, 'UTF-8');
        $safeUpdated = htmlspecialchars($updatedText, ENT_QUOTES, 'UTF-8');
        $safeRepoUrl = htmlspecialchars($repoUrl, ENT_QUOTES, 'UTF-8');
        $safeWrapperClass = htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8');

        echo '<div class="' . $safeWrapperClass . '">';
        echo '<div>'
            . webchessTranslate("WebChess") . ' '
            . webchessTranslate("Version") . ' '
            . $safeVersion . ', '
            . webchessTranslate("last updated") . ' '
            . $safeUpdated
            . '</div>';
        echo '<div><a href="' . $safeRepoUrl . '" class="alert-link">'
            . webchessTranslate("WebChess")
            . '</a> '
            . webchessTranslate("is Free Software released under the GNU General Public License (GPL).")
            . '</div>';
        echo '</div>';
    }
}