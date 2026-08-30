<?php
declare(strict_types=1);

/**
 * MySQL connection uses UTC (see db.php: SET time_zone = '+00:00').
 * Naive DATETIME strings from the DB are UTC wall-clock times.
 * JavaScript must receive ISO 8601 with Z so Date parses the correct instant.
 */
function pcvc_mysql_utc_to_iso8601_z(?string $mysqlDatetime): ?string
{
    if ($mysqlDatetime === null) {
        return null;
    }
    $s = trim($mysqlDatetime);
    if ($s === '') {
        return null;
    }

    $utc = new DateTimeZone('UTC');
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i:s.u'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $s, $utc);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d\TH:i:s') . 'Z';
        }
    }

    try {
        return (new DateTimeImmutable($s, $utc))->format('Y-m-d\TH:i:s') . 'Z';
    } catch (Throwable $e) {
        return $s;
    }
}
