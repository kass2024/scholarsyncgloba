<?php
declare(strict_types=1);

/**
 * Compute the application base path dynamically.
 *
 * Examples:
 * - Local XAMPP:   /Xander/student/index.php  -> base "/Xander"
 * - cPanel root:   /student/index.php        -> base ""
 * - API endpoint:  /Xander/api/x.php         -> base "/Xander"
 */
function pcvc_app_base_path(): string
{
    $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($sn === '') return '';

    foreach (['/student/', '/api/'] as $seg) {
        $pos = strpos($sn, $seg);
        if ($pos !== false) {
            $base = rtrim(substr($sn, 0, $pos), '/');
            return $base;
        }
    }

    $dir = rtrim(dirname($sn), '/');
    return $dir === '/' ? '' : $dir;
}

/**
 * Build an absolute-path URL within the app, respecting base path.
 * Pass paths like "/student/index.php" or "/student-login.php".
 */
function pcvc_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return pcvc_app_base_path() ?: '/';
    if ($path[0] !== '/') $path = '/' . $path;
    return pcvc_app_base_path() . $path;
}

