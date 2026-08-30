<?php
declare(strict_types=1);

/**
 * Francophonie Mobility — stored upload path helpers (supports multiple academic files as JSON).
 */

function fm_normalize_rel_path(string $path): string
{
    return str_replace('\\', '/', ltrim(trim($path), '/'));
}

function fm_project_root(): string
{
    return dirname(__DIR__);
}

function fm_abs_upload_path(string $relativePath): ?string
{
    $rel = fm_normalize_rel_path($relativePath);
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    $candidate = fm_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $full = realpath($candidate);
    return ($full && is_file($full)) ? $full : null;
}

function fm_path_is_under(string $fullPath, string $baseDir): bool
{
    $full = strtolower(str_replace('\\', '/', $fullPath));
    $base = strtolower(str_replace('\\', '/', rtrim($baseDir, '/\\')));
    return $full === $base || str_starts_with($full, $base . '/');
}

function fm_rel_from_abs(string $absPath): string
{
    $root = realpath(fm_project_root());
    if (!$root) {
        return '';
    }
    $abs = str_replace('\\', '/', $absPath);
    $rootNorm = str_replace('\\', '/', $root);
    if (!str_starts_with(strtolower($abs), strtolower($rootNorm))) {
        return '';
    }
    return ltrim(substr($abs, strlen($rootNorm)), '/');
}

function fm_parse_stored_files(?string $raw): array
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

function fm_encode_stored_files(array $paths): string
{
    $paths = array_values(array_filter(array_map(static fn($p) => trim((string) $p), $paths)));
    return $paths === [] ? '' : json_encode($paths, JSON_UNESCAPED_SLASHES);
}

function fm_collect_post_file_paths(string $primaryKey, ?string $legacyKey = null): array
{
    $paths = [];
    $raw = trim((string) ($_POST[$primaryKey] ?? ''));
    if ($raw !== '') {
        if ($raw[0] === '[') {
            $paths = fm_parse_stored_files($raw);
        } else {
            $paths[] = $raw;
        }
    }
    if ($legacyKey && isset($_POST[$legacyKey])) {
        $legacy = trim((string) $_POST[$legacyKey]);
        if ($legacy !== '' && !in_array($legacy, $paths, true)) {
            $paths[] = $legacy;
        }
    }
    if (isset($_POST[$primaryKey . '_list']) && is_array($_POST[$primaryKey . '_list'])) {
        foreach ($_POST[$primaryKey . '_list'] as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $paths, true)) {
                $paths[] = $p;
            }
        }
    }
    return array_values(array_filter($paths));
}

function fm_move_upload_permanent(string $tempPath, string $label): string
{
    $tmpBase = realpath(fm_project_root() . '/uploads/tmp');
    $fullTemp = fm_abs_upload_path($tempPath);

    if (!$tmpBase || !$fullTemp || !fm_path_is_under($fullTemp, $tmpBase)) {
        throw new RuntimeException('Could not find uploaded file for ' . $label . '. Please re-upload it.');
    }

    $permDir = fm_project_root() . '/uploads/francophonie_mobility';
    if (!is_dir($permDir) && !mkdir($permDir, 0755, true) && !is_dir($permDir)) {
        throw new RuntimeException('Cannot create upload directory');
    }

    $ext = strtolower(pathinfo($fullTemp, PATHINFO_EXTENSION));
    $filename = 'fm_' . date('Ymd') . '_' . uniqid() . '_' . $label . ($ext ? '.' . $ext : '');
    $dest = $permDir . '/' . $filename;

    if (!rename($fullTemp, $dest)) {
        throw new RuntimeException('Failed to store ' . $label);
    }

    return 'uploads/francophonie_mobility/' . $filename;
}

/**
 * Accept a path already in permanent storage, or move legacy tmp uploads on submit.
 */
function fm_finalize_stored_path(string $storedPath, string $label): string
{
    $storedPath = trim($storedPath);
    if ($storedPath === '') {
        return '';
    }

    $permBase = realpath(fm_project_root() . '/uploads/francophonie_mobility');
    $tmpBase = realpath(fm_project_root() . '/uploads/tmp');
    $full = fm_abs_upload_path($storedPath);

    if (!$full) {
        return '';
    }

    if ($permBase && fm_path_is_under($full, $permBase)) {
        return fm_rel_from_abs($full);
    }

    if ($tmpBase && fm_path_is_under($full, $tmpBase)) {
        return fm_move_upload_permanent($storedPath, $label);
    }

    return '';
}

function fm_finalize_stored_path_optional(string $storedPath, string $label): string
{
    try {
        return fm_finalize_stored_path($storedPath, $label);
    } catch (RuntimeException) {
        return '';
    }
}

function fm_finalize_upload_list(array $paths, string $labelPrefix): array
{
    $stored = [];
    $i = 0;
    foreach ($paths as $p) {
        $p = trim((string) $p);
        if ($p === '') {
            continue;
        }
        $i++;
        $final = fm_finalize_stored_path($p, $labelPrefix . ($i > 1 ? '_' . $i : ''));
        if ($final !== '') {
            $stored[] = $final;
        }
    }
    return $stored;
}

/** @deprecated use fm_finalize_stored_path_optional */
function fm_move_upload_optional(string $tempPath, string $label): string
{
    return fm_finalize_stored_path_optional($tempPath, $label);
}

/** @deprecated use fm_finalize_upload_list */
function fm_move_upload_list(array $tempPaths, string $labelPrefix): array
{
    return fm_finalize_upload_list($tempPaths, $labelPrefix);
}

function fm_human_field_label(string $key): string
{
    $map = [
        'full_name' => 'Full Name',
        'date_of_birth' => 'Date of Birth',
        'passport_number' => 'Passport Number',
        'address' => 'Full Address',
        'age' => 'Age',
        'nationality' => 'Nationality',
        'country_of_residence' => 'Current Country of Residence',
        'profession' => 'Current Profession / Occupation',
        'years_experience' => 'Years of Professional Experience',
        'email' => 'Email',
        'phone_area_code' => 'Phone (country code)',
        'phone_number' => 'Phone number',
        'highest_degree' => 'Highest Degree Obtained',
        'field_of_study' => 'Field of Study',
        'university_name' => 'University / College Name',
        'country_of_study' => 'Country of Study',
        'graduation_year' => 'Graduation Year',
        'french_level' => 'French Level',
        'french_professional' => 'Can you work professionally in French?',
        'english_level' => 'English Level',
        'english_professional' => 'Can you work professionally in English?',
        'has_wes' => 'Do you have WES?',
        'cv_file' => 'CV',
        'french_cert_file' => 'French Certificate',
        'english_cert_file' => 'English Certificate',
        'academic_docs_file' => 'Academic Documents',
        'video_file' => 'Introduction Video',
    ];
    return $map[$key] ?? ucwords(str_replace('_', ' ', $key));
}
