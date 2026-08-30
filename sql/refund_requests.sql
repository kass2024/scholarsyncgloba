-- Refund requests — auto-executed by helpers/refund_requests_schema.php on first use.
-- Manual run (optional): import into the ScholarSync Global database.

CREATE TABLE IF NOT EXISTS refund_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_id VARCHAR(32) NOT NULL,
    student_application_id INT UNSIGNED NULL DEFAULT NULL,
    student_portal_account_id INT UNSIGNED NULL DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(64) DEFAULT NULL,
    application_id VARCHAR(64) DEFAULT NULL,
    is_existing_student TINYINT(1) NOT NULL DEFAULT 0,
    service_paid_for VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    reason TEXT NOT NULL,
    payment_proof_file VARCHAR(500) DEFAULT NULL,
    request_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    admin_comment TEXT NULL,
    internal_note TEXT NULL,
    submitted_by VARCHAR(16) NOT NULL DEFAULT 'public',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reference_id (reference_id),
    INDEX idx_request_status (request_status),
    INDEX idx_email (email),
    INDEX idx_student_application (student_application_id),
    INDEX idx_portal_account (student_portal_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refund_request_status_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    refund_request_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(32) NULL DEFAULT NULL,
    new_status VARCHAR(32) NOT NULL,
    admin_id INT UNSIGNED NULL DEFAULT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refund_request_id (refund_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
