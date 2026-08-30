<?php
declare(strict_types=1);

/**
 * Web-relative path (for HTML src) from a calling script's directory (__DIR__) to the ScholarSync Global logo.
 */
function scholarsync_brand_logo_href(string $callerDir): string
{
    $callerDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $callerDir), DIRECTORY_SEPARATOR);
    $dir = $callerDir;
    $projectRoot = null;

    for ($i = 0; $i < 16; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'header.php') && is_dir($dir . DIRECTORY_SEPARATOR . 'includes')) {
            $projectRoot = $dir;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    if ($projectRoot === null) {
        return 'assets/brand/scholarsync-mark.svg';
    }

    $depth = 0;
    $walk = $callerDir;
    while ($walk !== $projectRoot) {
        $parent = dirname($walk);
        if ($parent === $walk) {
            return 'assets/brand/scholarsync-mark.svg';
        }
        $depth++;
        $walk = $parent;
    }

    $suffix = is_file($projectRoot . DIRECTORY_SEPARATOR . 'scholarsync-global-logo.jpg')
        ? 'scholarsync-global-logo.jpg'
        : 'assets/brand/scholarsync-mark.svg';

    return str_repeat('../', $depth) . $suffix;
}

/**
 * Path relative to project root for Dompdf/HTML templates (chroot = project root).
 */
function scholarsync_brand_logo_pdf_path(): string
{
    $root = dirname(__DIR__);
    if (is_file($root . DIRECTORY_SEPARATOR . 'scholarsync-global-logo.jpg')) {
        return 'scholarsync-global-logo.jpg';
    }
    return 'assets/brand/scholarsync-mark.svg';
}
