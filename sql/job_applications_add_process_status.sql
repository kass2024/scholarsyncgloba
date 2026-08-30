-- Optional manual migration (also applied automatically on first load of job-applicant.php / API)
ALTER TABLE `job_applications`
  ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' COMMENT 'Workflow stage';

-- Allowed values (application logic): submitted, under_review, waiting_decision, final_decision, closed
