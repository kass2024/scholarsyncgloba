-- ScholarSync Global branding cleanup for system-owned admin records.
-- Password hashes are intentionally preserved.
USE `visawgnz_scholarsyncglobal`;

UPDATE `admins`
SET
  `first_name` = 'ScholarSync',
  `last_name` = 'Global',
  `full_name` = 'ScholarSync Global',
  `email` = 'infos@scholarsyncglobal.ca'
WHERE `id` = 1 AND `username` = 'admin';

UPDATE `admins`
SET
  `username` = 'Delphine @2025'
WHERE `id` = 34 AND `username` = 'Parrot @2025';

UPDATE `admins`
SET
  `username` = 'scholarsync_staff',
  `first_name` = 'ScholarSync',
  `last_name` = 'Staff',
  `full_name` = 'ScholarSync Global Staff',
  `email` = 'infos@scholarsyncglobal.ca'
WHERE `id` = 70 AND `username` = 'parrot';
