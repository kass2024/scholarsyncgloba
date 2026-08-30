<?php
declare(strict_types=1);

/**
 * Ensure commission_requests table and columns exist (idempotent).
 */
function pcvc_ensure_commission_requests_schema(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS commission_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(64) DEFAULT NULL,
            street_address VARCHAR(255) DEFAULT NULL,
            address_line_2 VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            state VARCHAR(100) DEFAULT NULL,
            postal_code VARCHAR(32) DEFAULT NULL,
            recruited_name VARCHAR(255) DEFAULT NULL,
            recruited_phone VARCHAR(64) DEFAULT NULL,
            country_applied VARCHAR(100) DEFAULT NULL,
            loan_status VARCHAR(32) DEFAULT NULL,
            visa_status VARCHAR(32) DEFAULT NULL,
            contract_signed VARCHAR(8) DEFAULT NULL,
            comments TEXT NULL,
            submission_date DATE NOT NULL,
            signature VARCHAR(255) NOT NULL,
            recruited_student_id INT UNSIGNED NULL DEFAULT NULL,
            amount_usd DECIMAL(12,2) NULL DEFAULT NULL,
            amount_rwf DECIMAL(15,2) NULL DEFAULT NULL,
            fx_rate_used DECIMAL(14,6) NULL DEFAULT NULL,
            commission_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            request_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            paid_rwf_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            internal_note TEXT NULL,
            last_momo_transaction_id VARCHAR(96) NULL DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_request_status (request_status),
            INDEX idx_submission_date (submission_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    @$conn->query($sql);

    $tbl = 'commission_requests';
    $chk = $conn->query("SHOW TABLES LIKE '{$tbl}'");
    if (!$chk || $chk->num_rows === 0) {
        return;
    }
    $chk->free();

    $have = [];
    $cols = $conn->query('SHOW COLUMNS FROM `' . $tbl . '`');
    if ($cols) {
        while ($r = $cols->fetch_assoc()) {
            $have[strtolower((string) ($r['Field'] ?? ''))] = true;
        }
        $cols->free();
    }

    $alters = [];
    if (empty($have['amount_usd'])) {
        $alters[] = 'ADD COLUMN `amount_usd` DECIMAL(12,2) NULL DEFAULT NULL';
    }
    if (empty($have['amount_rwf'])) {
        $alters[] = 'ADD COLUMN `amount_rwf` DECIMAL(15,2) NULL DEFAULT NULL';
    }
    if (empty($have['fx_rate_used'])) {
        $alters[] = 'ADD COLUMN `fx_rate_used` DECIMAL(14,6) NULL DEFAULT NULL';
    }
    if (empty($have['commission_currency'])) {
        $alters[] = "ADD COLUMN `commission_currency` VARCHAR(3) NOT NULL DEFAULT 'USD'";
    }
    if (empty($have['request_status'])) {
        $alters[] = "ADD COLUMN `request_status` VARCHAR(32) NOT NULL DEFAULT 'pending'";
    }
    if (empty($have['paid_rwf_total'])) {
        $alters[] = 'ADD COLUMN `paid_rwf_total` DECIMAL(15,2) NOT NULL DEFAULT 0';
    }
    if (empty($have['internal_note'])) {
        $alters[] = 'ADD COLUMN `internal_note` TEXT NULL';
    }
    if (empty($have['rejection_reason'])) {
        $alters[] = 'ADD COLUMN `rejection_reason` TEXT NULL';
    }
    if (empty($have['last_momo_transaction_id'])) {
        $alters[] = 'ADD COLUMN `last_momo_transaction_id` VARCHAR(96) NULL DEFAULT NULL';
    }

    foreach ($alters as $fragment) {
        @$conn->query("ALTER TABLE `{$tbl}` {$fragment}");
    }
}
