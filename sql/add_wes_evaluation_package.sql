-- =============================================================================
-- 7.17 WES EVALUATION – INTERNATIONAL EQUIVALENCE
-- Seeds fee_packages + fee_items for Applicants Management → Record Payment
-- Database: visawgnz_scholarsyncglobal
-- Safe to re-run (skips rows that already exist)
-- =============================================================================

START TRANSACTION;

-- 1) Package header (dropdown in Record Application Payment)
INSERT INTO fee_packages (code, title, currency, total_amount, total_expected)
SELECT
    'p716',
    '7.17 WES EVALUATION - INTERNATIONAL EQUIVALENCE',
    'CAD',
    900.00,
    900.00
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fee_packages WHERE code = 'p716'
);

SET @wes_pkg_id := (
    SELECT id FROM fee_packages WHERE code = 'p716' LIMIT 1
);

-- 2) Pay-per-item fee lines (Fee Items — Pay Per Item)
INSERT INTO fee_items (package_id, name, amount, currency)
SELECT @wes_pkg_id, v.name, v.amount, 'CAD'
FROM (
    SELECT '1. Professional Service Fees' AS name, 200.00 AS amount
    UNION ALL SELECT '2. Application & Processing Costs', 300.00
    UNION ALL SELECT '3. University & Verification Coordination', 100.00
    UNION ALL SELECT '4. Document Shipping & Delivery Expenses', 100.00
    UNION ALL SELECT '5. Time, Administrative Work & Follow-up', 200.00
) AS v
WHERE @wes_pkg_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM fee_items fi
      WHERE fi.package_id = @wes_pkg_id
        AND fi.name = v.name
  );

COMMIT;

-- -----------------------------------------------------------------------------
-- Verify
-- -----------------------------------------------------------------------------
SELECT id, code, title, total_amount, currency
FROM fee_packages
WHERE code = 'p716';

SELECT fi.id, fi.package_id, fi.name, fi.amount, fi.currency
FROM fee_items fi
JOIN fee_packages fp ON fp.id = fi.package_id
WHERE fp.code = 'p716'
ORDER BY fi.id;

-- -----------------------------------------------------------------------------
-- Example: record one payment for an applicant (replace placeholders)
-- application_id = student_applications.id
-- fee_item_id    = id from fee_items query above
-- -----------------------------------------------------------------------------
/*
INSERT INTO application_payments (
    application_id,
    source_table,
    fee_item_id,
    amount_paid,
    payment_method,
    payment_comment,
    status,
    paid_at
) VALUES (
    123,                              -- applicant id
    'student_applications',
    52,                               -- fee item id (e.g. Professional Service Fees)
    200.00,
    'Cash',
    'WES evaluation — item 1',
    'PAID',
    NOW()
);
*/
