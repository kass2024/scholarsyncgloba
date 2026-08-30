<?php
declare(strict_types=1);

/**
 * Ensure `caq` pipeline flag exists on student_applications (Canada / Quebec CAQ letter).
 */
function pcvc_ensure_caq_column(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $r = $conn->query("SHOW COLUMNS FROM `student_applications` LIKE 'caq'");
    if ($r && $r->num_rows > 0) {
        return;
    }

    // Place after admit when possible.
    $ok = @$conn->query("ALTER TABLE `student_applications` ADD COLUMN `caq` TINYINT(1) NOT NULL DEFAULT 0 AFTER `admit`");
    if (!$ok) {
        @$conn->query("ALTER TABLE `student_applications` ADD COLUMN `caq` TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function pcvc_destination_is_canada(?string $destination): bool
{
    $d = strtolower(trim((string) $destination));
    if ($d === '') {
        return false;
    }
    return strpos($d, 'canada') !== false || $d === 'ca' || strpos($d, 'québec') !== false || strpos($d, 'quebec') !== false;
}
