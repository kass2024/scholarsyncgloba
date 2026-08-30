<?php
/**
 * Create or repair chat_sessions / chat_messages for chat-api.php.
 * Handles "Table ... doesn't exist" and "doesn't exist in engine" (broken/corrupt metadata).
 */

declare(strict_types=1);

/**
 * Run CREATE TABLE statements (idempotent).
 */
function pcvc_chat_create_tables(mysqli $conn): bool
{
    $sqlSessions = <<<'SQL'
CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `session_id` varchar(128) NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'ai',
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_chat_sessions_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $sqlMessages = <<<'SQL'
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `sender` varchar(16) NOT NULL,
  `message` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_session` (`session_id`),
  KEY `idx_chat_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    if (!$conn->query($sqlSessions)) {
        return false;
    }
    return (bool) $conn->query($sqlMessages);
}

/**
 * Drop and recreate (clears chat history). Use when table is corrupt.
 */
function pcvc_chat_reinstall_tables(mysqli $conn): bool
{
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    $conn->query('DROP TABLE IF EXISTS `chat_messages`');
    $conn->query('DROP TABLE IF EXISTS `chat_sessions`');
    $conn->query('SET FOREIGN_KEY_CHECKS=1');

    return pcvc_chat_create_tables($conn);
}

/**
 * Verify tables are readable; returns false if broken/missing.
 */
function pcvc_chat_tables_usable(mysqli $conn): bool
{
    $r = @$conn->query('SELECT 1 FROM `chat_sessions` LIMIT 1');
    if ($r === false) {
        return false;
    }
    $r->free();
    $r2 = @$conn->query('SELECT 1 FROM `chat_messages` LIMIT 1');
    if ($r2 === false) {
        return false;
    }
    $r2->free();

    return true;
}

/**
 * Ensure chat tables exist and work; auto-repair if InnoDB reports "doesn't exist in engine".
 */
function pcvc_ensure_chat_tables(mysqli $conn): void
{
    $log = static function (string $stage, array $ctx = []): void {
        if (function_exists('pcvc_chat_log')) {
            pcvc_chat_log($stage, $ctx);
        }
    };

    if (!pcvc_chat_create_tables($conn)) {
        $log('chat_tables_create_failed', ['err' => $conn->error]);
    }

    if (pcvc_chat_tables_usable($conn)) {
        $log('chat_tables_ok');

        return;
    }

    $log('chat_tables_unusable_repairing', ['err' => $conn->error]);

    if (!pcvc_chat_reinstall_tables($conn)) {
        $log('chat_tables_repair_failed', ['err' => $conn->error]);
        throw new RuntimeException('Chat database tables could not be created: ' . $conn->error);
    }

    if (!pcvc_chat_tables_usable($conn)) {
        throw new RuntimeException('Chat database tables are still not usable after repair.');
    }

    $log('chat_tables_repaired');
}
