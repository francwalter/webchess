<?php
// $Id: newuser.php,v 1.8 2010/08/15 07:54:46 sandking Exp $

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
require_once 'footer.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* load settings */
if (!isset($_CONFIG))
    require_once 'config.php';

if (!isset($_CHESSUTILS))
    require_once 'chessutils.php';

require "lang.php";
$requestedLang = null;
if (isset($_GET['lang'])) {
    $requestedLang = strtolower((string)$_GET['lang']);
} elseif (isset($_POST['lang'])) {
    $requestedLang = strtolower((string)$_POST['lang']);
}

if ($requestedLang !== null && in_array($requestedLang, ['en', 'de'], true)) {
    $_SESSION['pref_language'] = $requestedLang;
}
require 'csrf.php';

fixOldPHPVersions();
if ($CFG_NEW_USERS_ALLOWED == false) {
    die(webchessTranslate("Not Authorized!"));
}
?>

<!doctype html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles/theme.css">
    <link rel="stylesheet" href="styles/userlogin.css">
    <script src="javascript/cookies.js" defer></script>
    <script src="javascript/theme.js"></script>
    <title><?php echo webchessTranslate("WebChess") . " :: " . webchessTranslate("Create New User"); ?></title>

    <script>
        function validateForm() {
            const f = document.userdata;

            if (
                f.txtFirstName.value.trim() === "" ||
                f.txtLastName.value.trim() === "" ||
                f.txtNick.value.trim() === "" ||
                f.pwdPassword.value === ""
            ) {
                alert(<?php echo json_encode(webchessTranslate("Sorry, all personal info fields are required and must be filled out.")); ?>);
                return;
            }

            if (f.pwdPassword.value === f.pwdPassword2.value) {
                f.submit();
            } else {
                alert(<?php echo json_encode(webchessTranslate("Sorry, the two password fields don't match. Please try again.")); ?>);
            }
        }
    </script>
</head>

<body class="newuser-page">
<div id="header">
    <div id="heading"><?php echo webchessTranslate("WebChess") . " :: " . webchessTranslate("Create New User"); ?></div>
    <div class="language-switch">
        <a href="newuser.php?lang=en">English</a>
        <a href="newuser.php?lang=de">Deutsch</a>
    </div>
</div>
<div id="ctr" align="center">
    <div class="preferences">
        <div class="preferences-form">
            <form name="userdata" method="post" action="mainmenu.php">
                <div class="form-block">
                    <h1><?php echo webchessTranslate("Personal information"); ?></h1>
                    <div class="inputlabel"><?php echo webchessTranslate("First Name"); ?></div>
                    <div><input name="txtFirstName" type="text" class="inputbox"
                                value="<?php echo htmlspecialchars((string)($_POST['txtFirstName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="inputlabel"><?php echo webchessTranslate("Last Name"); ?></div>
                    <div><input name="txtLastName" type="text" class="inputbox"
                                value="<?php echo htmlspecialchars((string)($_POST['txtLastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="inputlabel"><?php echo webchessTranslate("Nick"); ?></div>
                    <div>
                        <input name="txtNick" type="text" class="inputbox"/>
                        <?php
                        /* this var is set to true in mainmenu.php */
                        if (isset($tmpNewUser) && $tmpNewUser)
                            echo '<div class="warning">' . webchessTranslate("Sorry, that nick is already in use.") . '</div>'; ?>
                    </div>
                    <div class="inputlabel"><?php echo webchessTranslate("Password"); ?></div>
                    <div><input name="pwdPassword" type="password" class="inputbox"/></div>
                    <div class="inputlabel"><?php echo webchessTranslate("Password Confirmation"); ?></div>
                    <div><input name="pwdPassword2" type="password" class="inputbox"/></div>
                    <h1><?php echo webchessTranslate("Personal preferences"); ?></h1>
                    <div class="inputlabel"><?php echo webchessTranslate("History"); ?></div>
                    <div class="inputbox">
                        <div><input name="rdoHistory" type="radio" value="pgn"
                                    checked="checked"/> <?php echo webchessTranslate("PGN"); ?></div>
                        <div><input name="rdoHistory" type="radio"
                                    value="verbose"/> <?php echo webchessTranslate("Verbose"); ?></div>
                    </div>
                    <div class="inputlabel"><?php echo webchessTranslate("History Layout"); ?></div>
                    <div class="inputbox">
                        <div><input name="rdoHistorylayout" type="radio" value="columns"
                                    checked="checked"/> <?php echo webchessTranslate("Columns (Scoresheet)"); ?></div>
                        <div><input name="rdoHistorylayout" type="radio"
                                    value="paragraph"/> <?php echo webchessTranslate("Paragraph"); ?></div>
                    </div>
                    <div class="inputlabel"><?php echo webchessTranslate("Theme"); ?></div>
                    <div class="inputbox">
                        <div><input name="rdoTheme" type="radio" value="beholder" checked="checked"/> <a
                                    href="http://www.beholder.co.uk"><?php echo webchessTranslate("Beholder"); ?></a>
                        </div>
                        <div><input name="rdoTheme" type="radio"
                                    value="plain"/> <?php echo webchessTranslate("Plain"); ?></div>
                    </div>
                    <div class="inputlabel"><?php echo webchessTranslate("Auto-reload") . " (" . webchessTranslate("min:") . ($CFG_MINAUTORELOAD) . " " . webchessTranslate("secs") . ")"; ?></div>
                    <div><input type="text" class="inputbox" name="txtReload"
                                value="<?php echo($CFG_MINAUTORELOAD); ?>"/></div>
                    <?php if ($CFG_USEEMAILNOTIFICATION) { ?>
                        <div class="inputlabel"><?php echo webchessTranslate("Email notification"); ?></div>
                        <div><input type="text" class="inputbox" name="txtEmailNotification"
                                    value="<?php echo htmlspecialchars((string)($_POST['txtEmailNotification'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"/>
                        </div>
                        <div class="instruction"><?php echo webchessTranslate("Enter a valid email address if you would like to be notified when your opponent makes a move. Leave blank otherwise."); ?></div>
                    <?php } ?>

                    <input name="btnCreate" type="button" class="button"
                           value="<?php echo webchessTranslate("Register"); ?>" onClick="validateForm()"/>
                    <input name="btnCancel" type="button" class="button"
                           value="<?php echo webchessTranslate("Cancel"); ?>"
                           onClick="window.open('index.php', '_self')"/>

                    <input name="ToDo" value="NewUser" type="hidden"/>
                    <?php echo webchessCsrfField(); ?>
                </div>
            </form>
        </div>
        <div class="login-text">
            <div class="ctr"><img src="images/webchess.jpg" width="65" height="92" alt="security"/></div>
            <p><?php echo webchessTranslate("Welcome to WebChess!"); ?></p>
            <p><?php echo webchessTranslate("You must remember your nick and password to be able to gain access to WebChess."); ?></p>
        </div>
        <div class="clr"></div>
    </div>
</div>
<div id="break"></div>
<?php webchessRenderFooter(null, null, null, 'footer ctr small'); ?>
</body>
</html>