<?php
// $Id: makeConfig.php,v 1.1 2010/08/23 02:53:46 sandking Exp $

/*
    This file is part of WebChess. http://webchess.sourceforge.net
	Copyright 2010 Jonathan Evraire, Rodrigo Flores, rigao

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

//This file is called by install.php (case 3 of the switch, before it ends the installation
//procedure. It has two functions: first, to create a new user if told so. Second,
//to make the config.php file and put it for download.

//This function creates the user that will interact with the database.
//Normally it would be expected for this file to be located in install.php
//but coding the installer has yeld to the conclusion that it is easier to put it
//right here.
function createUser($new_user, $new_password, $user, $password, $server, $DBname, &$error = '')
{
    if (!webchessIsSafeSqlIdentifier($DBname) || !webchessIsSafeSqlIdentifier($new_user)) {
        $error = 'invalid_identifier';
        return false;
    }

    $dbh = @mysqli_connect($server, $user, $password);
    if (!$dbh) {
        $error = 'connect: ' . (string)mysqli_connect_error();
        return false;
    }

    if (!mysqli_select_db($dbh, $DBname)) {
        $error = 'select_db: ' . mysqli_error($dbh);
        mysqli_close($dbh);
        return false;
    }

    $escapedPassword = mysqli_real_escape_string($dbh, (string)$new_password);
    $query = "GRANT SELECT, INSERT, UPDATE, DELETE ON `".$DBname."`.* TO '".$new_user."'@'%' IDENTIFIED BY '".$escapedPassword."'";

    $result = mysqli_query($dbh, $query);
    if (!$result) {
        $error = 'grant: ' . mysqli_error($dbh);
    }

    mysqli_close($dbh);
    return $result;
}

function webchessIsSafeSqlIdentifier($value)
{
    return (is_string($value) && preg_match('/^[A-Za-z0-9_]+$/', $value) === 1);
}

function webchessPhpStringLiteral($value)
{
    return var_export((string)$value, true);
}

/* debug flag */
define("DEBUG", 0);

$installerLang = strtolower((string)($_POST['installer_lang'] ?? 'en'));
if (!in_array($installerLang, array('en', 'de'), true)) {
    $installerLang = 'en';
}

function installerMsg($key, $lang)
{
    $messages = array(
        'de' => array(
            'db_user_required' => 'Bitte Datenbank-Benutzer angeben oder "Gleichen Benutzer wie in Schritt 2 verwenden" aktivieren.',
            'db_user_create_failed' => 'Der Datenbankbenutzer konnte nicht erstellt werden. Bitte Eingaben und Rechte pruefen.',
            'invalid_db_or_user' => 'Ungueltiger Datenbankname oder Benutzername.',
        ),
        'en' => array(
            'db_user_required' => 'Please provide a database username or enable "Use the same user as in step 2."',
            'db_user_create_failed' => 'The database user could not be created. Please check credentials and permissions.',
            'invalid_db_or_user' => 'Invalid database name or username.',
        ),
    );

    return isset($messages[$lang][$key]) ? $messages[$lang][$key] : $key;
}

function installerError($message)
{
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Installer Error</title></head><body style="font-family:Arial,Helvetica,sans-serif;background:#f5f7fb;padding:24px;">';
    echo '<div style="max-width:980px;margin:0 auto;background:#fff;border:1px solid #f5c2c7;color:#842029;padding:12px 14px;border-radius:8px;">';
    echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '</div></body></html>';
    exit;
}

//We see if the administrator has provided a new user to be used. If it is so
//(FALSE case) we will create the new user, if it is not (TRUE case) we won't
//create any user.
if (isset($_POST['reuse']) && $_POST['reuse'] == 'true') {
    //In case we reuse, there is no need to create the new username.
    $user = $_POST['user_last'];
    $pass = $_POST['pass_last'];
    $new_user = $_POST['user_last'];
    $new_pass = $_POST['pass_last'];
} else {
    $new_user = trim((string)($_POST['user'] ?? ''));
    $new_pass = (string)($_POST['pass'] ?? '');
    $user = (string)($_POST['user_last'] ?? '');
    $pass = (string)($_POST['pass_last'] ?? '');

    if ($new_user === '') {
        installerError(installerMsg('db_user_required', $installerLang));
    }
    $dbUserError = '';
    $result = createUser($new_user, $new_pass, $user, $pass, $_POST['server'], $_POST['DBname'], $dbUserError);
    if ($result !== true) {
        $safeDetail = trim((string)$dbUserError);
        if ($safeDetail === '') {
            $safeDetail = 'unknown';
        }
        installerError(installerMsg('db_user_create_failed', $installerLang) . ' [' . $safeDetail . ']');
    }
}

$new_user = trim((string)$new_user);
if ($new_user === '') {
    installerError(installerMsg('db_user_required', $installerLang));
}

$server = isset($_POST['server']) ? (string)$_POST['server'] : '';
$dbName = isset($_POST['DBname']) ? (string)$_POST['DBname'] : '';
if (!webchessIsSafeSqlIdentifier($dbName) || !webchessIsSafeSqlIdentifier((string)$new_user)) {
    installerError(installerMsg('invalid_db_or_user', $installerLang));
}

header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP 1.1
header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP 1.1
header('Pragma: no-cache'); // HTTP 1.0
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-Transfer-Encoding: none');
header('Content-Type: text/php; name="config.php"');
header('Content-Disposition: attachment; filename="config.php"');
// header("Content-length: $content_len");

//Here we start the second part. We start to generate config.php.
echo "<?php\n";
echo "\$_CONFIG=true;\n\n";

echo "/* database settings */\n";
echo "\$CFG_SERVER = " . webchessPhpStringLiteral($server) . ";\n";
echo "\$CFG_USER = " . webchessPhpStringLiteral($new_user) . ";\n";
echo "\$CFG_PASSWORD = " . webchessPhpStringLiteral($new_pass) . ";\n";
echo "\$CFG_DATABASE = " . webchessPhpStringLiteral($dbName) . ";\n";
echo "\n/* server settings */\n";

$sessionTimeoutMinutes = isset($_POST['timeout']) ? (int)$_POST['timeout'] : 30;
if ($sessionTimeoutMinutes <= 0)
    $sessionTimeoutMinutes = 30;
echo "\$CFG_SESSIONTIMEOUT = " . ($sessionTimeoutMinutes * 60) . ";\n";
if (!isset($_POST['login_lock_seconds']) || (int)$_POST['login_lock_seconds'] <= 0)
    $_POST['login_lock_seconds'] = 900;
echo "\$CFG_LOGIN_LOCK_SECONDS = " . (int)$_POST['login_lock_seconds'] . ";\n";
echo "\$CFG_EXPIREGAME = " . (int)$_POST['expire'] . ";\n";
echo "\$CFG_MINAUTORELOAD = " . (int)$_POST['autoreload'] . ";\n";


echo "\$CFG_USEEMAILNOTIFICATION = ";
if (isset($_POST['mail_not']) && $_POST['mail_not'] == '1')
    echo "TRUE;\n";
else echo "FALSE;\n";

echo "\$CFG_MAILADRESS = " . webchessPhpStringLiteral(isset($_POST['mail_adr']) ? $_POST['mail_adr'] : '') . ";\n";

echo "\$CFG_MAINPAGE = " . webchessPhpStringLiteral(isset($_POST['url']) ? $_POST['url'] : '') . ";\n";
echo "\$CFG_MAXUSERS = " . (int)$_POST['maxUsers'] . ";\n";
echo "\$CFG_MAXACTIVEGAMES = " . (int)$_POST['maxGames'] . ";\n";
echo "\$CFG_NICKCHANGEALLOWED = ";
if (isset($_POST['changeNick']) && $_POST['changeNick'] == '1')
    echo "TRUE;\n";
else echo "FALSE;\n";

echo "\$CFG_NEW_USERS_ALLOWED = ";
if (isset($_POST['newUsers']) && $_POST['newUsers'] == '1')
    echo "TRUE;\n";
else echo "FALSE;\n";

echo "\$CFG_BOARDSQUARESIZE = " . (int)$_POST['size'] . ";\n";

?>
    /* mysql table names */
    $CFG_TABLE['communication'] = "communication";
    $CFG_TABLE['games'] = "games";
    $CFG_TABLE['history'] = "history";
    $CFG_TABLE['messages'] = "messages";
    $CFG_TABLE['pieces'] = "pieces";
    $CFG_TABLE['players'] = "players";
    $CFG_TABLE['preferences'] = "preferences";

<?php
$imageExt = (isset($_POST['imageExtension']) && in_array($_POST['imageExtension'], array('gif', 'png'), true)) ? $_POST['imageExtension'] : 'png';
echo "\$CFG_IMAGE_EXT = " . webchessPhpStringLiteral($imageExt) . ";\n";
echo "?>";
