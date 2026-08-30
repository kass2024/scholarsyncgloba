-- =============================================================
-- Smart Brochure Sharing — schema
-- Database: visawgnz_scholarsyncglobal
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Brochures uploaded for a region
CREATE TABLE IF NOT EXISTS `marketing_brochures` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id`        INT UNSIGNED NULL,
  `title`            VARCHAR(255) NOT NULL,
  `slug`             VARCHAR(190) NOT NULL,
  `description`      TEXT NULL,
  `pdf_filename`     VARCHAR(255) NOT NULL,
  `pdf_path`         VARCHAR(500) NOT NULL,
  `pdf_size_bytes`   INT UNSIGNED NOT NULL DEFAULT 0,
  `cover_image`      VARCHAR(500) NULL,
  `extracted_text`   LONGTEXT NULL,
  `view_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  `share_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`       INT UNSIGNED NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_brochure_slug` (`slug`),
  KEY `idx_brochure_region` (`region_id`),
  KEY `idx_brochure_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each share event (link copied, sent to whatsapp, email, ...)
CREATE TABLE IF NOT EXISTS `marketing_brochure_shares` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brochure_id`      INT UNSIGNED NOT NULL,
  `share_token`      VARCHAR(64) NOT NULL,
  `recipient_name`   VARCHAR(190) NULL,
  `recipient_phone`  VARCHAR(40) NULL,
  `recipient_email`  VARCHAR(190) NULL,
  `channel`          ENUM('copy','whatsapp','email','sms','other') NOT NULL DEFAULT 'copy',
  `matched_table`    VARCHAR(80) NULL,
  `matched_row_id`   INT UNSIGNED NULL,
  `is_new_contact`   TINYINT(1) NOT NULL DEFAULT 0,
  `shared_by`        INT UNSIGNED NULL,
  `notes`            VARCHAR(255) NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_opened_at`   TIMESTAMP NULL DEFAULT NULL,
  `open_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_share_brochure` (`brochure_id`),
  KEY `idx_share_phone` (`recipient_phone`),
  KEY `idx_share_token` (`share_token`),
  CONSTRAINT `fk_share_brochure`
    FOREIGN KEY (`brochure_id`) REFERENCES `marketing_brochures` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
