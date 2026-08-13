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

	/* load settings */
	if (!isset($_CONFIG))
		require 'config.php';

    require_once "lang.php";
?>
<!DOCTYPE html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebChess :: <?php echo gettext("Login");?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/theme.css" type="text/css" />
    <script type="text/javascript" src="javascript/theme.js"></script>
    <script type="text/javascript" src="javascript/cookies.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin-top: 100px; }
        .card { border-radius: 1rem; }
        .btn-primary { background-color: #007bff; border: none; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; border: none; }
    </style>
    <script type="text/javascript">
    var visitordata = null;
    var cookieDomain = null;
    function storeLogin()
    {
        if(document.loginForm.remember.checked)
        {
            visitordata.nick = document.loginForm.txtNick.value;
            visitordata.pwd = document.loginForm.pwdPassword.value;
            visitordata.store();
        }
    }
    window.onload = function()
    {
        document.loginForm.onsubmit = function(){storeLogin();};

        cookieDomain = document.domain;
        var idx = cookieDomain.lastIndexOf('.');
        idx = cookieDomain.lastIndexOf('.', idx-1);
        if(idx == -1)
            cookieDomain = '.' + cookieDomain;
        else
            cookieDomain = cookieDomain.substr(idx);
        visitordata = new Cookie(document, "WebChess", 2400, '/', cookieDomain);
        if(visitordata.load())
        {
            if(visitordata.nick)
                document.loginForm.txtNick.value = visitordata.nick;
            if(visitordata.pwd)
                document.loginForm.pwdPassword.value = visitordata.pwd;
            document.loginForm.remember.checked = true;
        }
        document.loginForm.txtNick.focus();
    }
    </script>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="login-container w-100">
        <div style="text-align: right; margin-bottom: 20px;">
            <button id="theme-toggle-btn" class="btn btn-link" onclick="toggleTheme()" title="Toggle Dark Mode" style="text-decoration:none;">🌙</button>
        </div>
        <div class="card shadow-lg">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <img src="images/webchess.jpg" width="65" height="92" alt="security" class="mb-3 rounded">
                    <h2 class="fw-bold mb-2 text-uppercase">WebChess</h2>
                    <p class="text-muted mb-4"><?php echo gettext("Welcome to") . " WebChess!";?></p>
                </div>

                <form name="loginForm" id="loginForm" method="post" action="mainmenu.php">
                    <div class="form-outline mb-4 text-start">
                        <label class="form-label" for="txtNick"><?php echo gettext("Username");?></label>
                        <input type="text" id="txtNick" name="txtNick" class="form-control form-control-lg" required />
                    </div>

                    <div class="form-outline mb-4 text-start">
                        <label class="form-label" for="pwdPassword"><?php echo gettext("Password");?></label>
                        <input type="password" id="pwdPassword" name="pwdPassword" class="form-control form-control-lg" required />
                    </div>

                    <div class="form-check d-flex justify-content-start mb-4">
                        <input class="form-check-input" type="checkbox" value="" id="remember" name="remember" />
                        <label class="form-check-label ms-2" for="remember">
                            <?php echo gettext("Remember me");?>
                        </label>
                    </div>

                    <input name="ToDo" value="Login" type="hidden" />

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" type="submit"><?php echo gettext("Login");?></button>
                        <?php if($CFG_NEW_USERS_ALLOWED==true) { ?>
                            <button class="btn btn-outline-secondary btn-lg" type="button" onClick="window.location.href='newuser.php'"><?php echo gettext("New Account");?></button>
                        <?php } ?>
                    </div>
                </form>

                <p class="mt-4 text-muted small">
                    <?php echo gettext("Use a valid username and password to gain access to WebChess.");?>
                </p>
            </div>
        </div>

        <div class="mt-4 text-center text-muted small">
            <div><?php echo "WebChess Version 1.0.0, last updated August 15, 2010"?></div>
            <div><a href="http://webchess.sourceforge.net/" class="text-decoration-none"><?php echo gettext("WebChess");?></a> <?php echo gettext("is Free Software released under the GNU General Public License (GPL).");?></div>
        </div>
    </div>
</div>

<noscript>
    <div class="alert alert-danger mt-3 text-center" role="alert">
        !Warning! JavaScript must be enabled for proper operation of WebChess
    </div>
</noscript>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
