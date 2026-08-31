<?php
declare(strict_types=1);

/**
 * Backward-compatible database include for API/helper files.
 *
 * Keep this path pointed at the application's single database bootstrap.
 * A second hardcoded connection here caused API requests to use root with
 * no password and return an HTML database error instead of JSON.
 */
require_once dirname(__DIR__) . '/db.php';
