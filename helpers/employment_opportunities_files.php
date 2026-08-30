<?php
declare(strict_types=1);

/**
 * Employment Opportunities — upload path helpers (multi academic docs as JSON).
 */

function eo_normalize_rel_path(string $path): string
{
    return str_replace('\\', '/', ltrim(trim($path), '/'));
}

function eo_project_root(): string
{
    return dirname(__DIR__);
}

function eo_abs_upload_path(string $relativePath): ?string
{
    $rel = eo_normalize_rel_path($relativePath);
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    if (!str_starts_with(strtolower($rel), 'uploads/employment_opportunities/')) {
        return null;
    }
    $candidate = eo_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $full = realpath($candidate);
    return ($full && is_file($full)) ? $full : null;
}

function eo_parse_stored_files(?string $raw): array
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }
    if ($raw[0] === '[') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn($p) => trim((string) $p), $decoded)));
    }
    if (str_contains($raw, '|')) {
        return array_values(array_filter(array_map('trim', explode('|', $raw))));
    }
    return [$raw];
}

function eo_encode_stored_files(array $paths): string
{
    $paths = array_values(array_filter(array_map(static fn($p) => trim((string) $p), $paths)));
    return $paths === [] ? '' : json_encode($paths, JSON_UNESCAPED_SLASHES);
}

function eo_collect_post_file_paths(string $primaryKey): array
{
    $paths = [];
    $raw = trim((string) ($_POST[$primaryKey] ?? ''));
    if ($raw !== '') {
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    $p = trim((string) $p);
                    if ($p !== '') {
                        $paths[] = $p;
                    }
                }
            }
        } else {
            $paths[] = $raw;
        }
    }
    if (!empty($_POST[$primaryKey . '_list']) && is_array($_POST[$primaryKey . '_list'])) {
        foreach ($_POST[$primaryKey . '_list'] as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $paths[] = $p;
            }
        }
    }
    return array_values(array_unique($paths));
}

function eo_validate_stored_path(string $rel): bool
{
    return eo_abs_upload_path($rel) !== null;
}
