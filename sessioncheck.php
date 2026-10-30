<?php
	// $Id: sessioncheck.php,v 1.6 2010/08/15 00:29:04 sandking Exp $

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

	require_once 'lang.php';

	if (!isset($_SESSION['playerID']))
		$_SESSION['playerID'] = -1;

	if ($_SESSION['playerID'] != -1)
	{
		$effectiveSessionTimeout = (int)$CFG_SESSIONTIMEOUT;
		if (isset($_SESSION['pref_sessiontimeout']) && is_numeric($_SESSION['pref_sessiontimeout']) && intval($_SESSION['pref_sessiontimeout']) >= 60)
			$effectiveSessionTimeout = intval($_SESSION['pref_sessiontimeout']);

		if (time() - $_SESSION['lastInputTime'] >= $effectiveSessionTimeout)
			$_SESSION['playerID'] = -1;
		else if (!isset($_GET['autoreload']))
			$_SESSION['lastInputTime'] = time();
	}

	if ($_SESSION['playerID'] == -1)
	{
		?>
		<!DOCTYPE html>
		<html lang="<?php echo getGuiLanguage(); ?>">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>WebChess - <?php echo htmlspecialchars(webchessTranslate('Session timed out.'), ENT_QUOTES, 'UTF-8'); ?></title>
			<script type="text/javascript" src="javascript/theme.js"></script>
			<style>
				body {
					margin: 0;
					font-family: Arial, Helvetica, sans-serif;
					background: #f3f4f6;
					color: #1f2937;
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 100vh;
				}
				html[data-bs-theme="dark"] body,
				body[data-theme="dark"] {
					background: #14171d;
					color: #e8edf5;
				}
				.wc-timeout-box {
					max-width: 720px;
					width: calc(100% - 40px);
					padding: 40px 32px;
					background: #ffffff;
					border: 2px solid #dc3545;
					border-radius: 16px;
					text-align: center;
					box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
				}
				html[data-bs-theme="dark"] .wc-timeout-box,
				body[data-theme="dark"] .wc-timeout-box {
					background: #1f2430;
					border-color: #dc3545;
					box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
				}
				.wc-timeout-box h1 {
					margin: 0 0 20px;
					font-size: 2.2rem;
					color: #b02a37;
				}
				html[data-bs-theme="dark"] .wc-timeout-box h1,
				body[data-theme="dark"] .wc-timeout-box h1 {
					color: #ff6b7a;
				}
				.wc-timeout-box p {
					margin: 0 0 26px;
					font-size: 1.3rem;
					line-height: 1.4;
				}
				.wc-timeout-login {
					display: inline-block;
					padding: 12px 24px;
					font-size: 1.1rem;
					font-weight: bold;
					color: #fff;
					background: #0d6efd;
					text-decoration: none;
					border-radius: 8px;
				}
				.wc-timeout-login:hover {
					background: #0b5ed7;
				}
			</style>
		</head>
		<body>
			<div class="wc-timeout-box">
				<h1><?php echo htmlspecialchars(webchessTranslate('Session timed out.'), ENT_QUOTES, 'UTF-8'); ?></h1>
				<p><?php echo htmlspecialchars(webchessTranslate('Please login again to continue.'), ENT_QUOTES, 'UTF-8'); ?></p>
				<a class="wc-timeout-login" href="index.php"><?php echo htmlspecialchars(webchessTranslate('Login'), ENT_QUOTES, 'UTF-8'); ?></a>
			</div>
		</body>
		</html>
		<?php
		exit();
	}
