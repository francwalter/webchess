<?php
/*
    Centralized security defaults for WebChess.
*/

if (!function_exists('webchessIsHttps')) {
    function webchessIsHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }
}

if (!function_exists('webchessConfigureSessionSecurity')) {
    function webchessConfigureSessionSecurity()
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');

        if (webchessIsHttps()) {
            @ini_set('session.cookie_secure', '1');
        }
    }
}

if (!function_exists('webchessApplySecurityHeaders')) {
    function webchessApplySecurityHeaders()
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}

webchessConfigureSessionSecurity();
webchessApplySecurityHeaders();

