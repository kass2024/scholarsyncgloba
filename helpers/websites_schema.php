<?php
declare(strict_types=1);

/**
 * Website management — schema helper (idempotent).
 */
function pcvc_ensure_websites_schema(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $sql = "
        CREATE TABLE IF NOT EXISTS `websites` (
            `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `serial_no`       VARCHAR(32) NOT NULL,
            `website_name`    VARCHAR(255) NOT NULL,
            `website_link`    VARCHAR(500) NULL,
            `admin_username`  VARCHAR(255) NOT NULL DEFAULT '',
            `admin_password`  VARCHAR(255) NOT NULL DEFAULT '',
            `email_link`      VARCHAR(500) NULL,
            `status`          ENUM('Active','Not Active') NOT NULL DEFAULT 'Active',
            `notes`           TEXT NULL,
            `created_by`      INT UNSIGNED NULL,
            `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_website_serial` (`serial_no`),
            KEY `idx_website_status` (`status`),
            KEY `idx_website_name` (`website_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sql)) {
        error_log('websites table create failed: ' . $conn->error);
    }

    $hasCol = static function (string $col) use ($conn): bool {
        $col = $conn->real_escape_string($col);
        $r = $conn->query("SHOW COLUMNS FROM `websites` LIKE '$col'");
        $ok = $r && $r->num_rows > 0;
        if ($r) {
            $r->free();
        }
        return $ok;
    };

    if (!$hasCol('email_link')) {
        @$conn->query("ALTER TABLE `websites` ADD COLUMN `email_link` VARCHAR(500) NULL DEFAULT NULL AFTER `admin_password`");
    }

    $sqlEmails = "
        CREATE TABLE IF NOT EXISTS `website_email_accounts` (
            `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `website_id`      INT UNSIGNED NOT NULL,
            `serial_no`       VARCHAR(48) NOT NULL,
            `email_username`  VARCHAR(255) NOT NULL DEFAULT '',
            `email_password`  VARCHAR(255) NOT NULL DEFAULT '',
            `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_email_account_serial` (`serial_no`),
            KEY `idx_email_account_website` (`website_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sqlEmails)) {
        error_log('website_email_accounts create failed: ' . $conn->error);
    }
}

function pcvc_generate_email_account_serial(mysqli $conn, int $websiteId, string $websiteSerial): string
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM website_email_accounts WHERE website_id = ?');
    $stmt->bind_param('i', $websiteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $count = (int) ($row['total'] ?? 0) + 1;
    return $websiteSerial . '-EM-' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
}

/**
 * @return array<int, list<array<string, mixed>>>
 */
function pcvc_fetch_website_email_accounts_grouped(mysqli $conn): array
{
    $grouped = [];
    $q = $conn->query('SELECT * FROM website_email_accounts ORDER BY website_id ASC, id ASC');
    if (!$q) {
        return $grouped;
    }
    while ($row = $q->fetch_assoc()) {
        $wid = (int) $row['website_id'];
        $grouped[$wid][] = $row;
    }
    return $grouped;
}

/**
 * @param array<int, array<string, mixed>> $accounts
 */
function pcvc_save_website_email_accounts(mysqli $conn, int $websiteId, string $websiteSerial, array $accounts): void
{
    foreach ($accounts as $acc) {
        $accId = (int) ($acc['id'] ?? 0);
        $delete = !empty($acc['delete']);
        $username = trim((string) ($acc['username'] ?? ''));
        $password = trim((string) ($acc['password'] ?? ''));

        if ($accId > 0 && $delete) {
            $stmt = $conn->prepare('DELETE FROM website_email_accounts WHERE id = ? AND website_id = ?');
            $stmt->bind_param('ii', $accId, $websiteId);
            $stmt->execute();
            $stmt->close();
            continue;
        }

        if ($accId > 0) {
            if ($password !== '') {
                $stmt = $conn->prepare('
                    UPDATE website_email_accounts
                    SET email_username = ?, email_password = ?
                    WHERE id = ? AND website_id = ?
                ');
                $stmt->bind_param('ssii', $username, $password, $accId, $websiteId);
            } else {
                $stmt = $conn->prepare('
                    UPDATE website_email_accounts
                    SET email_username = ?
                    WHERE id = ? AND website_id = ?
                ');
                $stmt->bind_param('sii', $username, $accId, $websiteId);
            }
            $stmt->execute();
            $stmt->close();
            continue;
        }

        if ($username === '' && $password === '') {
            continue;
        }

        $serial = pcvc_generate_email_account_serial($conn, $websiteId, $websiteSerial);
        $stmt = $conn->prepare('
            INSERT INTO website_email_accounts (website_id, serial_no, email_username, email_password)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->bind_param('isss', $websiteId, $serial, $username, $password);
        $stmt->execute();
        $stmt->close();
    }
}
