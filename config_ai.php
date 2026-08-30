<?php
declare(strict_types=1);

/**
 * Legacy alias for scripts that still expect AI_API_KEY.
 * Set OPENAI_API_KEY in .env (loaded via helpers/env_bootstrap.php).
 */
require_once __DIR__ . '/helpers/env_bootstrap.php';

if (!defined('AI_API_KEY')) {
    define('AI_API_KEY', pcvc_env('OPENAI_API_KEY'));
}
