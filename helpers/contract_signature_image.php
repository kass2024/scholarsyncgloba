<?php
declare(strict_types=1);

/**
 * Decode a signature data-URL to raw image bytes (no GD required).
 */
function contract_signature_raw_bytes(string $dataUrl): ?string
{
    if (!preg_match('#^data:image/(png|jpeg);base64,(.+)$#i', $dataUrl, $m)) {
        return null;
    }

    $raw = base64_decode($m[2], true);

    return ($raw !== false && $raw !== '') ? $raw : null;
}

/**
 * Build a data-URL suitable for <img src> (normalized when GD is available).
 */
function contract_signature_display_data_url(string $dataUrl): ?string
{
    if (!extension_loaded('gd')) {
        return str_starts_with($dataUrl, 'data:image/') ? $dataUrl : null;
    }

    $png = contract_signature_to_display_png($dataUrl);
    if ($png !== null) {
        return 'data:image/png;base64,' . base64_encode($png);
    }

    return str_starts_with($dataUrl, 'data:image/') ? $dataUrl : null;
}

/**
 * Flatten signature data-URL PNGs onto a white background for reliable display/print.
 */
function contract_signature_to_display_png(string $dataUrl): ?string
{
    $raw = contract_signature_raw_bytes($dataUrl);
    if ($raw === null) {
        return null;
    }

    if (!extension_loaded('gd')) {
        return null;
    }

    $src = @imagecreatefromstring($raw);
    if ($src === false) {
        return null;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        imagedestroy($src);
        return null;
    }

    $dest = imagecreatetruecolor($w, $h);
    if ($dest === false) {
        imagedestroy($src);
        return null;
    }

    $white = imagecolorallocate($dest, 255, 255, 255);
    $black = imagecolorallocate($dest, 0, 0, 0);
    imagefill($dest, 0, 0, $white);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($src, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;

            // GD: 127 = transparent, 0 = opaque — keep all visible ink (including black strokes)
            if ($alpha < 120) {
                imagesetpixel($dest, $x, $y, $black);
            }
        }
    }

    imagedestroy($src);

    ob_start();
    imagepng($dest);
    $png = ob_get_clean();
    imagedestroy($dest);

    return is_string($png) && $png !== '' ? $png : null;
}

function contract_signature_storage_dir(): string
{
    return dirname(__DIR__) . '/uploads/contracts_special';
}

function contract_signature_standard_file_path(int $contractId): string
{
    return dirname(__DIR__) . '/uploads/contracts/signature_' . $contractId . '.png';
}

/**
 * Save signature PNG for standard student contracts (returns absolute path or null).
 */
function contract_signature_save_standard_png(int $contractId, string $dataUrl): ?string
{
    $png = contract_signature_to_display_png($dataUrl);
    if ($png === null) {
        $png = contract_signature_raw_bytes($dataUrl);
    }
    if ($png === null || $png === '') {
        return null;
    }

    $dir = dirname(__DIR__) . '/uploads/contracts';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $path = contract_signature_standard_file_path($contractId);
    if (file_put_contents($path, $png) === false) {
        return null;
    }

    return $path;
}

function contract_signature_file_path(int $contractId): string
{
    return contract_signature_storage_dir() . '/signature_' . $contractId . '.png';
}

/**
 * Save normalized signature PNG to disk (returns public relative URL or null).
 */
function contract_signature_save_png_file(int $contractId, string $dataUrl): ?string
{
    $png = contract_signature_to_display_png($dataUrl);
    if ($png === null) {
        $png = contract_signature_raw_bytes($dataUrl);
    }
    if ($png === null || $png === '') {
        return null;
    }

    $dir = contract_signature_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $path = contract_signature_file_path($contractId);
    if (file_put_contents($path, $png) === false) {
        return null;
    }

    return contract_signature_public_url($contractId);
}

function contract_signature_public_url(int $contractId): ?string
{
    $path = contract_signature_file_path($contractId);
    if (!is_file($path)) {
        return null;
    }

    return 'uploads/contracts_special/signature_' . $contractId . '.png?v=' . filemtime($path);
}

/**
 * Resolve best <img src> for a signed contract (file URL preferred).
 */
function contract_signature_resolve_img_src(int $contractId, string $dataUrl): string
{
    $fileUrl = contract_signature_public_url($contractId);
    if ($fileUrl !== null) {
        return $fileUrl;
    }

    if (contract_signature_save_png_file($contractId, $dataUrl) !== null) {
        return contract_signature_public_url($contractId) ?? '';
    }

    return contract_signature_display_data_url($dataUrl) ?? $dataUrl;
}
