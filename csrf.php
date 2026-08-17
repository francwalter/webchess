<?php
/*
    Lightweight CSRF helpers for WebChess.
*/

if (!function_exists('webchessCsrfToken')) {
	function webchessCsrfToken()
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
			session_start();

		if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '')
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

		return $_SESSION['csrf_token'];
	}
}

if (!function_exists('webchessCsrfField')) {
	function webchessCsrfField()
	{
		return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(webchessCsrfToken(), ENT_QUOTES, 'UTF-8') . '" />';
	}
}

if (!function_exists('webchessCsrfValidateRequest')) {
	function webchessCsrfValidateRequest($token = null)
	{
		if ($token === null)
			$token = $_POST['csrf_token'] ?? '';

		return isset($_SESSION['csrf_token'])
			&& is_string($_SESSION['csrf_token'])
			&& is_string($token)
			&& hash_equals($_SESSION['csrf_token'], $token);
	}
}

if (!function_exists('webchessCsrfRegenerateToken')) {
	function webchessCsrfRegenerateToken()
	{
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		return $_SESSION['csrf_token'];
	}
}

