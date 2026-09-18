<?php
    require_once 'security.php';
    session_start();

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

  /* On first run, route users to installer automatically. */
  if (!isset($_CONFIG))
  {
		if (!is_file(__DIR__ . '/config.php'))
		{
			header('Location: install.php?reason=no_config');
			exit();
		}

    require 'config.php';

    /* If DB is not reachable or schema is missing, continue with installer. */
    $isInstalled = false;
    if (function_exists('mysqli_connect'))
    {
      $tmpDbh = @mysqli_connect($CFG_SERVER, $CFG_USER, $CFG_PASSWORD, $CFG_DATABASE);
      if ($tmpDbh)
      {
        $requiredTables = array('players', 'games', 'history', 'messages', 'pieces', 'preferences', 'communication');
        $isInstalled = true;
        foreach ($requiredTables as $tableKey)
        {
          $tableName = isset($CFG_TABLE[$tableKey]) ? $CFG_TABLE[$tableKey] : $tableKey;
          $tableExists = false;
          $stmtTableExists = @mysqli_prepare(
            $tmpDbh,
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1"
          );
          if ($stmtTableExists)
          {
            mysqli_stmt_bind_param($stmtTableExists, "ss", $CFG_DATABASE, $tableName);
            mysqli_stmt_execute($stmtTableExists);
            $tableResult = mysqli_stmt_get_result($stmtTableExists);
            $tableExists = ($tableResult && mysqli_num_rows($tableResult) > 0);
            mysqli_stmt_close($stmtTableExists);
          }

          if (!$tableExists)
          {
            $isInstalled = false;
            break;
          }
        }
        mysqli_close($tmpDbh);
      }
    }

		if (!$isInstalled)
		{
			header('Location: install.php?reason=not_installed');
			exit();
		}
  }

    require_once "lang.php";
    require_once "csrf.php";
?>
<!DOCTYPE html>
<html lang="<?php echo getGuiLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebChess :: <?php echo webchessTranslate("Login");?></title>
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
            if (typeof visitordata.pwd !== 'undefined')
                delete visitordata.pwd;
            visitordata.store();
        }
        else if (visitordata)
        {
            visitordata.remove();
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
            if (typeof visitordata.pwd !== 'undefined')
            {
                delete visitordata.pwd;
                visitordata.store();
            }
            document.loginForm.remember.checked = !!visitordata.nick;
        }
        document.loginForm.txtNick.focus();
    }
    </script>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="login-container w-100">
        <div style="text-align: right; margin-bottom: 20px;">
            <button id="theme-toggle-btn" class="btn btn-link" onclick="toggleTheme()" data-title-dark="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>" data-title-light="<?php echo htmlspecialchars(webchessTranslate('Switch to Light Mode'), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(webchessTranslate('Switch to Dark Mode'), ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration:none;">&#9790;</button>
        </div>
        <div class="card shadow-lg">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <img src="images/webchess.jpg" width="65" height="92" alt="security" class="mb-3 rounded">
                    <h2 class="fw-bold mb-2 text-uppercase">WebChess</h2>
                    <p class="text-muted mb-4"><?php echo webchessTranslate("Welcome to") . " WebChess!";?></p>
                </div>

                <form name="loginForm" id="loginForm" method="post" action="mainmenu.php">
                    <div class="form-outline mb-4 text-start">
                        <label class="form-label" for="txtNick"><?php echo webchessTranslate("Username");?></label>
                        <input type="text" id="txtNick" name="txtNick" class="form-control form-control-lg" required />
                    </div>

                    <div class="form-outline mb-4 text-start">
                        <label class="form-label" for="pwdPassword"><?php echo webchessTranslate("Password");?></label>
                        <input type="password" id="pwdPassword" name="pwdPassword" class="form-control form-control-lg" required />
                    </div>

                    <div class="form-check d-flex justify-content-start mb-4">
                        <input class="form-check-input" type="checkbox" value="" id="remember" name="remember" />
                        <label class="form-check-label ms-2" for="remember">
                            <?php echo webchessTranslate("Remember me");?>
                        </label>
                    </div>

                    <input name="ToDo" value="Login" type="hidden" />
                    <?php echo webchessCsrfField(); ?>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" type="submit"><?php echo webchessTranslate("Login");?></button>
                        <?php if($CFG_NEW_USERS_ALLOWED==true) { ?>
                            <button class="btn btn-outline-secondary btn-lg" type="button" onClick="window.location.href='newuser.php'"><?php echo webchessTranslate("New Account");?></button>
                        <?php } ?>
                    </div>
                </form>

                <p class="mt-4 text-muted small">
                    <?php echo webchessTranslate("Use a valid username and password to gain access to WebChess.");?>
                </p>
            </div>
        </div>

        <div class="mt-4 text-center text-muted small">
            <div><?php echo "WebChess Version 1.0.0, last updated August 15, 2010"?></div>
            <div><a href="http://webchess.sourceforge.net/" class="text-decoration-none"><?php echo webchessTranslate("WebChess");?></a> <?php echo webchessTranslate("is Free Software released under the GNU General Public License (GPL).");?></div>
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

