<?php

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

require_once 'security.php';

session_start();

require 'csrf.php';
require 'chessutils.php';

/* check session status */
require 'sessioncheck.php';

if (!isset($_CONFIG)) {
    require 'config.php';
}

require 'connectdb.php';

$id = (int)$_SESSION['playerID'];

$uiTheme = (isset($_GET['theme']) && $_GET['theme'] === 'dark') ? 'dark' : 'light';
$bodyClass = ($uiTheme === 'dark') ? 'bg-dark text-light' : 'bg-light';

if (isset($_POST['newMessage'])) {
    if (!webchessCsrfValidateRequest()) {
        die('Invalid form token. Please reload and try again.');
    }

    $fromPerson = $id;
    $toPerson = (isset($_POST['to']) && $_POST['to'] !== '') ? (int)$_POST['to'] : 0;

    if ($toPerson > 0) {
        $mGame = (isset($_POST['forGame']) && $_POST['forGame'] !== '') ? (int)$_POST['forGame'] : null;
        if ($mGame !== null && !webchessPlayerOwnsGame($dbh, $mGame, $fromPerson)) {
            die('Unauthorized game selection.');
        }

        $msgtitle = trim((string)($_POST['txtTitle'] ?? ''));
        $msgtext = trim((string)($_POST['txtMessage'] ?? ''));
        $msgtype = '0'; // Always 0... yet..

        if ($mGame === null) {
            $stmt = mysqli_prepare(
                $dbh,
                'INSERT INTO ' . $CFG_TABLE['communication'] . ' (gameID,fromID,toID,title,text,postDate,expireDate,ack,commType) VALUES (NULL, ?, ?, ?, ?, NOW(), NULL, 0, ?)'
            );
            if (!$stmt) {
                die('Database error.');
            }
            mysqli_stmt_bind_param($stmt, 'iisss', $fromPerson, $toPerson, $msgtitle, $msgtext, $msgtype);
        } else {
            $stmt = mysqli_prepare(
                $dbh,
                'INSERT INTO ' . $CFG_TABLE['communication'] . ' (gameID,fromID,toID,title,text,postDate,expireDate,ack,commType) VALUES (?, ?, ?, ?, ?, NOW(), NULL, 0, ?)'
            );
            if (!$stmt) {
                die('Database error.');
            }
            mysqli_stmt_bind_param($stmt, 'iiisss', $mGame, $fromPerson, $toPerson, $msgtitle, $msgtext, $msgtype);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        ?>
        <!doctype html>
        <html lang="de" data-bs-theme="<?php echo htmlspecialchars($uiTheme, ENT_QUOTES, 'UTF-8'); ?>">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Nachricht gesendet</title>
            <style>
                html[data-bs-theme="dark"] body {
                    background-color: #14171d !important;
                    color: #e8edf5;
                }

                html[data-bs-theme="dark"] .card {
                    background-color: #1f2430;
                    color: #e8edf5;
                    border-color: #2b3242;
                }

                html[data-bs-theme="dark"] .card-header {
                    background-color: #181d29 !important;
                    color: #e8edf5 !important;
                    border-bottom-color: #2b3242 !important;
                }

                html[data-bs-theme="dark"] .form-control,
                html[data-bs-theme="dark"] .form-select {
                    background-color: #232a39 !important;
                    color: #e8edf5 !important;
                    border-color: #39445a !important;
                }

                html[data-bs-theme="dark"] .form-control:focus,
                html[data-bs-theme="dark"] .form-select:focus {
                    background-color: #232a39 !important;
                    color: #e8edf5 !important;
                }

                html[data-bs-theme="dark"] .form-label,
                html[data-bs-theme="dark"] .form-text {
                    color: #d6deea !important;
                }
            </style>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="<?php echo ($uiTheme === 'dark') ? 'bg-dark text-light' : 'bg-light'; ?>">
        <div class="container py-4">
            <div class="alert alert-success mb-0" role="alert">
                Message Sent!
            </div>
        </div>
        <script>
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'webchess-message-sent' }, '*');
            } else {
                window.close();
            }
        </script>
        </body>
        </html>
        <?php
        die();
    }
}

$to_get = isset($_GET['to']) ? (int)$_GET['to'] : 0;
$forGame_get = isset($_GET['forGame']) && $_GET['forGame'] !== '' ? (int)$_GET['forGame'] : 0;
if ($forGame_get > 0 && !webchessPlayerOwnsGame($dbh, $forGame_get, $id)) {
    $forGame_get = 0;
}

$stmtPlayers = mysqli_prepare(
    $dbh,
    'SELECT playerID, nick FROM ' . $CFG_TABLE['players'] . ' WHERE playerID <> ? ORDER BY nick ASC'
);
if (!$stmtPlayers) {
    die('Database error.');
}
mysqli_stmt_bind_param($stmtPlayers, 'i', $id);
mysqli_stmt_execute($stmtPlayers);
$tmpPlayers = mysqli_stmt_get_result($stmtPlayers);
?>
<!doctype html>
<html lang="de" data-bs-theme="<?php echo htmlspecialchars($uiTheme, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Send Message</title>
    <style>
        html[data-bs-theme="dark"] body {
            background-color: #14171d !important;
            color: #e8edf5;
        }

        html[data-bs-theme="dark"] .card {
            background-color: #1f2430;
            color: #e8edf5;
            border-color: #2b3242;
        }

        html[data-bs-theme="dark"] .card-header {
            background-color: #181d29 !important;
            color: #e8edf5 !important;
            border-bottom-color: #2b3242 !important;
        }

        html[data-bs-theme="dark"] .form-control,
        html[data-bs-theme="dark"] .form-select {
            background-color: #232a39 !important;
            color: #e8edf5 !important;
            border-color: #39445a !important;
        }

        html[data-bs-theme="dark"] .form-control:focus,
        html[data-bs-theme="dark"] .form-select:focus {
            background-color: #232a39 !important;
            color: #e8edf5 !important;
        }

        html[data-bs-theme="dark"] .form-label,
        html[data-bs-theme="dark"] .form-text {
            color: #d6deea !important;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="<?php echo $bodyClass; ?>">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <?php echo webchessTranslate("Send Message"); ?>
                </div>
                <div class="card-body">
                    <form action="sendmessage.php" method="post" name="FormName">
                        <?php echo webchessCsrfField(); ?>
                        <input type="hidden" name="from" value="<?php echo $id; ?>">
                        <?php if ($forGame_get > 0) { ?>
                            <input type="hidden" name="forGame" value="<?php echo $forGame_get; ?>">
                        <?php } ?>

                        <div class="mb-3">
                            <label for="to" class="form-label"><?php echo webchessTranslate("Message Recipient"); ?></label>
                            <select id="to" name="to" class="form-select">
                                <?php
                                while ($tmpPlayers && ($tmpPlayer = mysqli_fetch_assoc($tmpPlayers))) {
                                    if (!empty($tmpPlayer['nick'])) {
                                        $playerId = (int)$tmpPlayer['playerID'];
                                        $nick = htmlspecialchars($tmpPlayer['nick'], ENT_QUOTES, 'UTF-8');
                                        $selected = ($playerId === $to_get) ? ' selected="selected"' : '';
                                        echo '<option value="' . $playerId . '"' . $selected . '>' . $nick . "</option>\n";
                                    }
                                }
                                mysqli_stmt_close($stmtPlayers);
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="txtTitle" class="form-label"><?php echo webchessTranslate("Message Subject"); ?></label>
                            <input id="txtTitle" type="text" name="txtTitle" class="form-control" maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="txtMessage" class="form-label"><?php echo webchessTranslate("Message Text"); ?></label>
                            <textarea id="txtMessage" name="txtMessage" rows="10" tabindex="1" class="form-control"></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <input type="submit" name="newMessage" value="<?php echo webchessTranslate("Send Message"); ?>" class="btn btn-primary">
                            <input type="button" name="btnCancel" value="<?php echo webchessTranslate("Cancel"); ?>" class="btn btn-outline-secondary" onclick="closeMessageComposer();">                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function closeMessageComposer() {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'webchess-message-close' }, '*');
        } else {
            window.close();
        }
    }
</script>
</body>

</html>