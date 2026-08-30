-- Restore fee_packages after table repair (preserves ids used by fee_items)
-- Safe when fee_packages is empty; skips rows that already exist by id.

INSERT INTO fee_packages (id, code, title, currency, total_amount, total_expected)
SELECT * FROM (
    SELECT 1 AS id, 'p71' AS code, 'Study in the USA (Loan-Based)' AS title, 'USD' AS currency, 3000.00 AS total_amount, 3000.00 AS total_expected
    UNION ALL SELECT 2, 'p72', 'Study in the USA (Without Loan)', 'USD', 2300.00, 2300.00
    UNION ALL SELECT 3, 'p73', 'Study in Europe (Without Loan)', 'USD', 2000.00, 2000.00
    UNION ALL SELECT 4, 'p74', 'Study in Canada (Loan-Based)', 'CAD', 3500.00, 3500.00
    UNION ALL SELECT 5, 'p75', 'Study in Canada (Without Loan)', 'CAD', 2500.00, 2500.00
    UNION ALL SELECT 6, 'p76', 'Canada - High School Graduate (Loan-Based)', 'CAD', 4000.00, 4000.00
    UNION ALL SELECT 7, 'p77', 'Study in South Korea (Self-Sponsored)', 'USD', 2700.00, 2700.00
    UNION ALL SELECT 8, 'p78', 'South Korea Visitor Visa', 'USD', 2800.00, 2800.00
    UNION ALL SELECT 9, 'p79', 'Credit Transfer (Bachelor, Masters, PhD)', 'USD', 1620.00, 1620.00
    UNION ALL SELECT 10, 'p710', 'Canada Visit Visa', 'CAD', 3185.00, 3185.00
    UNION ALL SELECT 11, 'p711', 'USA Visit Visa', 'USD', 2685.00, 2685.00
    UNION ALL SELECT 12, 'p712', 'Europe Visit Visa', 'EUR', 2100.00, 2100.00
    UNION ALL SELECT 13, 'p713', 'Asia Visit Visa', 'USD', 2800.00, 2800.00
    UNION ALL SELECT 14, 'cust-20260522041852-', 'special visa', 'USD', 100.00, 100.00
    UNION ALL SELECT 15, 'ct-bachelor', 'Credit Transfer (Bachelor)', 'USD', 920.00, 920.00
    UNION ALL SELECT 16, 'ct-masters', 'Credit Transfer (Masters)', 'USD', 1220.00, 1220.00
    UNION ALL SELECT 17, 'ct-phd', 'Credit Transfer (PhD)', 'USD', 1620.00, 1620.00
    UNION ALL SELECT 18, 'upa-bach-app', 'UPAFA - Bachelor Application Fees', 'USD', 25.00, 25.00
    UNION ALL SELECT 19, 'p716', '7.17 WES EVALUATION - INTERNATIONAL EQUIVALENCE', 'CAD', 900.00, 900.00
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM fee_packages fp WHERE fp.id = seed.id);

-- Reset auto-increment above highest id
SET @max_id := (SELECT COALESCE(MAX(id), 0) FROM fee_packages);
SET @sql := CONCAT('ALTER TABLE fee_packages AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT id, code, title, total_amount, currency FROM fee_packages ORDER BY id;
