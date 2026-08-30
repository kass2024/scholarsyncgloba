-- =============================================================================
-- commission_requests: backfill amount_usd, amount_rwf, fx_rate_used from comments
--
-- Works on: MySQL 8.0.4+ and MariaDB 10.4+ (2-arg REGEXP_SUBSTR only — avoids #1582).
-- phpMyAdmin: select the ScholarSync Global database → SQL tab → paste → Go.
--
-- Edit @FX_RATE before running. Part A is the main fix for your known row IDs.
-- Part B/C are optional fallbacks (2-parameter REGEXP only).
-- =============================================================================

SET NAMES utf8mb4;
SET @FX_RATE := 1300.000000;
SET @CAD_TO_USD := 0.730000;

-- =============================================================================
-- PART A — Audited rows from your dump (no regex)
-- =============================================================================
UPDATE commission_requests SET
  amount_usd = CASE id
    WHEN 28 THEN 400.00
    WHEN 29 THEN 75.00
    WHEN 31 THEN 75.00
    WHEN 34 THEN 100.00
    WHEN 35 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 36 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 37 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 38 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 40 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 41 THEN 150.00
    WHEN 42 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 45 THEN 800.00
    WHEN 46 THEN 750.00
    WHEN 47 THEN 600.00
    WHEN 49 THEN 400.00
    WHEN 50 THEN 400.00
    WHEN 51 THEN 400.00
    WHEN 52 THEN ROUND(75 * @CAD_TO_USD, 2)
  END,
  fx_rate_used = @FX_RATE,
  amount_rwf = ROUND(CASE id
    WHEN 28 THEN 400.00
    WHEN 29 THEN 75.00
    WHEN 31 THEN 75.00
    WHEN 34 THEN 100.00
    WHEN 35 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 36 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 37 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 38 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 40 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 41 THEN 150.00
    WHEN 42 THEN ROUND(225 * @CAD_TO_USD, 2)
    WHEN 45 THEN 800.00
    WHEN 46 THEN 750.00
    WHEN 47 THEN 600.00
    WHEN 49 THEN 400.00
    WHEN 50 THEN 400.00
    WHEN 51 THEN 400.00
    WHEN 52 THEN ROUND(75 * @CAD_TO_USD, 2)
  END * @FX_RATE, 0)
WHERE id IN (28,29,31,34,35,36,37,38,40,41,42,45,46,47,49,50,51,52)
  AND (amount_usd IS NULL OR amount_usd <= 0)
  AND comments IS NOT NULL AND TRIM(comments) <> '';

UPDATE commission_requests SET
  amount_rwf = 108600.00,
  amount_usd = ROUND(108600 / NULLIF(@FX_RATE, 0), 2),
  fx_rate_used = @FX_RATE
WHERE id = 48
  AND (amount_usd IS NULL OR amount_usd <= 0);

-- =============================================================================
-- PART B — Optional: rows still empty; pattern "digits + usd" (LOWER + 2-arg REGEXP_SUBSTR)
-- =============================================================================
UPDATE commission_requests
SET
  amount_usd = CAST(
    REPLACE(
      TRIM(REPLACE(REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*usd'), 'usd', '')),
    ',', '') AS DECIMAL(12,2)
  ),
  fx_rate_used = @FX_RATE,
  amount_rwf = ROUND(
    CAST(
      REPLACE(
        TRIM(REPLACE(REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*usd'), 'usd', '')),
      ',', '') AS DECIMAL(14,6)
    ) * @FX_RATE, 0
  )
WHERE (amount_usd IS NULL OR amount_usd <= 0)
  AND comments IS NOT NULL AND TRIM(comments) <> ''
  AND LOWER(comments) REGEXP '[0-9][0-9,]*[[:space:]]*usd'
  AND REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*usd') IS NOT NULL;

-- digits then $
UPDATE commission_requests
SET
  amount_usd = CAST(
    REPLACE(
      TRIM(REGEXP_SUBSTR(comments, '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*\\$')),
    ',', '') AS DECIMAL(12,2)
  ),
  fx_rate_used = @FX_RATE,
  amount_rwf = ROUND(
    CAST(
      REPLACE(
        TRIM(REGEXP_SUBSTR(comments, '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*\\$')),
      ',', '') AS DECIMAL(14,6)
    ) * @FX_RATE, 0
  )
WHERE (amount_usd IS NULL OR amount_usd <= 0)
  AND comments IS NOT NULL AND TRIM(comments) <> ''
  AND comments REGEXP '[0-9][0-9,]*[[:space:]]*\\$'
  AND REGEXP_SUBSTR(comments, '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*\\$') IS NOT NULL;

-- $digits
UPDATE commission_requests
SET
  amount_usd = CAST(
    REPLACE(
      REPLACE(
        TRIM(REGEXP_SUBSTR(comments, '\\$[[:space:]]*[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?')),
      '$', ''), ',', '') AS DECIMAL(12,2)
  ),
  fx_rate_used = @FX_RATE,
  amount_rwf = ROUND(
    CAST(
      REPLACE(
        REPLACE(
          TRIM(REGEXP_SUBSTR(comments, '\\$[[:space:]]*[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?')),
        '$', ''), ',', '') AS DECIMAL(14,6)
    ) * @FX_RATE, 0
  )
WHERE (amount_usd IS NULL OR amount_usd <= 0)
  AND comments IS NOT NULL AND TRIM(comments) <> ''
  AND comments REGEXP '\\$[[:space:]]*[0-9]'
  AND REGEXP_SUBSTR(comments, '\\$[[:space:]]*[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?') IS NOT NULL;

-- digits + cad (LOWER)
UPDATE commission_requests
SET
  amount_usd = ROUND(
    CAST(
      REPLACE(
        TRIM(REPLACE(REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*cad'), 'cad', '')),
      ',', '') AS DECIMAL(14,6)
    ) * @CAD_TO_USD, 2
  ),
  fx_rate_used = @FX_RATE,
  amount_rwf = ROUND(
    CAST(
      REPLACE(
        TRIM(REPLACE(REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*cad'), 'cad', '')),
      ',', '') AS DECIMAL(14,6)
    ) * @CAD_TO_USD * @FX_RATE, 0
  )
WHERE (amount_usd IS NULL OR amount_usd <= 0)
  AND comments IS NOT NULL AND TRIM(comments) <> ''
  AND LOWER(comments) REGEXP '[0-9][0-9,]*[[:space:]]*cad'
  AND REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*cad') IS NOT NULL;

-- =============================================================================
-- PART C — Optional: first RWF / frw amount in LOWER(text)
-- =============================================================================
UPDATE commission_requests
SET
  amount_usd = ROUND(
    CAST(
      REPLACE(
        TRIM(REPLACE(REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*frw'), 'frw', '')),
      ',', '') AS DECIMAL(14,2)
    ) / NULLIF(@FX_RATE, 0), 2
  ),
  fx_rate_used = @FX_RATE,
  amount_rwf = CAST(
    REPLACE(
      TRIM(REPLACE(REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*frw'), 'frw', '')),
    ',', '') AS DECIMAL(15,2)
  )
WHERE (amount_usd IS NULL OR amount_usd <= 0)
  AND comments IS NOT NULL AND TRIM(comments) <> ''
  AND LOWER(comments) REGEXP '[0-9][0-9,]*[[:space:]]*frw'
  AND REGEXP_SUBSTR(LOWER(comments), '[0-9]{1,3}(,[0-9]{3})*(\\.[0-9]{1,2})?[[:space:]]*frw') IS NOT NULL;

-- =============================================================================
-- VERIFY (run manually)
-- =============================================================================
-- SELECT id, amount_usd, amount_rwf, fx_rate_used, LEFT(comments, 72) AS snippet
-- FROM commission_requests ORDER BY id;
