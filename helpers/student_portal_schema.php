<?php
declare(strict_types=1);

/**
 * Student portal schema helper (idempotent).
 * Creates only NEW portal tables in the existing DB.
 *
 * This does NOT modify existing `student_applications` rows/columns.
 */

function pcvc_student_portal_ensure_schema(mysqli $conn): void
{
    $dbRow = $conn->query('SELECT DATABASE() AS db')->fetch_assoc();
    $dbName = (string)($dbRow['db'] ?? '');

    $hasColumn = static function (string $table, string $column) use ($conn, $dbName): bool {
        if ($dbName === '') return false;
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
        $st = $conn->prepare($sql);
        if (!$st) return false;
        $st->bind_param('sss', $dbName, $table, $column);
        $st->execute();
        $ok = (bool)$st->get_result()->fetch_assoc();
        $st->close();
        return $ok;
    };

    // Accounts linked to student_applications.
    $sqlAccounts = "
        CREATE TABLE IF NOT EXISTS student_portal_accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_application_id INT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active','disabled') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_portal_accounts_email (email),
            KEY idx_student_portal_accounts_student_application_id (student_application_id),
            KEY idx_student_portal_accounts_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sqlAccounts)) {
        throw new RuntimeException('Failed creating student_portal_accounts: ' . $conn->error);
    }

    // If table existed with NOT NULL, relax it so accounts can be created for emails that exist in other tables.
    if ($hasColumn('student_portal_accounts', 'student_application_id')) {
        $conn->query("ALTER TABLE student_portal_accounts MODIFY student_application_id INT UNSIGNED NULL");
    }

    // Uploads owned by a student portal account.
    $sqlUploads = "
        CREATE TABLE IF NOT EXISTS student_portal_uploads (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_account_id INT UNSIGNED NOT NULL,
            doc_type VARCHAR(64) NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            storage_path VARCHAR(500) NOT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_student_portal_uploads_student_account_id (student_account_id),
            KEY idx_student_portal_uploads_doc_type (doc_type),
            KEY idx_student_portal_uploads_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sqlUploads)) {
        throw new RuntimeException('Failed creating student_portal_uploads: ' . $conn->error);
    }

    // If table existed before this change, ensure doc_type column exists.
    if (!$hasColumn('student_portal_uploads', 'doc_type')) {
        if (!$conn->query("ALTER TABLE student_portal_uploads ADD COLUMN doc_type VARCHAR(64) NULL AFTER student_account_id")) {
            if (stripos((string)$conn->error, 'Duplicate column') === false) {
                throw new RuntimeException('Failed adding doc_type to student_portal_uploads: ' . $conn->error);
            }
        } else {
            $conn->query("CREATE INDEX idx_student_portal_uploads_doc_type ON student_portal_uploads (doc_type)");
        }
    }
}

