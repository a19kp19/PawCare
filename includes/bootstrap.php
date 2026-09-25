<?php
/**
 * Loaded at the top of every page: configuration, helpers and the session.
 */
require_once __DIR__ . '/config.php';

date_default_timezone_set(TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/scheduling.php';
require_once __DIR__ . '/pets.php';
require_once __DIR__ . '/partials.php';

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (session_status() === PHP_SESSION_NONE) {
        session_name('PAWCARESESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    set_exception_handler('render_exception');
}
