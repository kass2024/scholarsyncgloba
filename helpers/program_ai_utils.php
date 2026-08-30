<?php
declare(strict_types=1);

/**
 * Shared helpers for Smart Paste (AI) program extraction and save.
 */

function pcvc_program_has_degree_marker(string $name): bool
{
    return (bool) preg_match(
        '/\b(BA|B\.A\.|BSc|B\.Sc|BEng|B\.Eng|MA|M\.A\.|MSc|M\.Sc|MBA|MEng|M\.Eng|PhD|Ph\.D|'
        . 'Bachelor|Master|Doctorate|Doctor of|Diploma|Certificate|Postgraduate|Undergraduate)\b/i',
        $name
    );
}

function pcvc_is_professional_course_name(string $name): bool
{
    return (bool) preg_match('/^professional course in /i', trim($name));
}

/**
 * Normalize Smart Paste output to "Professional Course in {title}" for vocational/college programs.
 */
function pcvc_normalize_ai_program_name(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        return '';
    }

    if (pcvc_is_professional_course_name($name)) {
        $subject = trim((string) preg_replace('/^professional course in /i', '', $name));
        return 'Professional Course in ' . $subject;
    }

    if (pcvc_program_has_degree_marker($name)) {
        return $name;
    }

    return 'Professional Course in ' . $name;
}

/**
 * Resolve (or create) the program_levels row used for professional/vocational courses.
 */
function pcvc_resolve_professional_course_level_id(mysqli $conn): ?int
{
    $sql = "
        SELECT id
        FROM program_levels
        WHERE abbreviation = 'PROC'
           OR LOWER(name) LIKE '%professional course%'
        ORDER BY (abbreviation = 'PROC') DESC, id ASC
        LIMIT 1
    ";

    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return (int) $row['id'];
    }

    $abbrev = 'PROC';
    $label = 'Professional Course';
    $stmt = mysqli_prepare($conn, 'INSERT INTO program_levels (abbreviation, name) VALUES (?, ?)');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $abbrev, $label);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $id > 0 ? $id : null;
}

/**
 * Pick program level in AI save mode.
 */
function pcvc_detect_ai_program_level(string $name, array $levelKeywords, mysqli $conn): ?int
{
    $name = pcvc_normalize_ai_program_name($name);
    if ($name === '') {
        return null;
    }

    if (pcvc_is_professional_course_name($name)) {
        return pcvc_resolve_professional_course_level_id($conn);
    }

    $lower = strtolower($name);
    foreach ($levelKeywords as $levelId => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($lower, strtolower($kw)) !== false) {
                return (int) $levelId;
            }
        }
    }

    // AI Smart Paste: default unmatched vocational-style titles to Professional Course.
    return pcvc_resolve_professional_course_level_id($conn);
}
