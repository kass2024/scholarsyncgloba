<?php
declare(strict_types=1);

/**
 * South Korea Event Participation — upload path helpers.
 */

function kep_normalize_rel_path(string $path): string
{
    return str_replace('\\', '/', ltrim(trim($path), '/'));
}

function kep_project_root(): string
{
    return dirname(__DIR__);
}

function kep_abs_upload_path(string $relativePath): ?string
{
    $rel = kep_normalize_rel_path($relativePath);
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    if (!str_starts_with(strtolower($rel), 'uploads/korea_event/')) {
        return null;
    }
    $candidate = kep_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $full = realpath($candidate);
    return ($full && is_file($full)) ? $full : null;
}

function kep_validate_stored_path(string $rel): bool
{
    return kep_abs_upload_path($rel) !== null;
}
