-- MoPay wallet transaction log: outbound (payroll, commission) and inbound (checkout webhooks).
-- Run once on the ScholarSync Global MySQL/MariaDB database.

CREATE TABLE IF NOT EXISTS mopay_wallet_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
  context_type VARCHAR(64) NOT NULL DEFAULT 'unknown',
  context_id VARCHAR(128) NULL,
  context_label VARCHAR(512) NULL,
  initiated_by_admin_id INT UNSIGNED NULL,
  recipient_msisdn VARCHAR(32) NULL COMMENT 'Digits: payee MSISDN for outbound; payer MSISDN for inbound when present',
  amount_rwf INT NOT NULL DEFAULT 0,
  currency VARCHAR(8) NOT NULL DEFAULT 'RWF',
  status ENUM('success','failed','pending') NOT NULL DEFAULT 'pending',
  gateway_transaction_id VARCHAR(128) NULL,
  mopay_flow VARCHAR(32) NULL COMMENT 'transfer | payment_api',
  http_status SMALLINT UNSIGNED NULL,
  error_message TEXT NULL,
  gateway_response_json MEDIUMTEXT NULL,
  meta_json TEXT NULL COMMENT 'JSON: commission_request_id, payroll_month, employee_admin_id, etc.',
  retry_of_id BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_mwt_created (created_at),
  KEY idx_mwt_status (status),
  KEY idx_mwt_context (context_type, context_id(64)),
  KEY idx_mwt_initiator (initiated_by_admin_id),
  KEY idx_mwt_direction (direction, created_at),
  KEY idx_mwt_retry_of (retry_of_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
