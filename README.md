# ScholarSync Global

ScholarSync Global is an independent admissions, mobility, student-portal, and
operations application.

## Configuration

Copy `.env.example` to `.env` and set the application database and SMTP values.
The production database is intentionally separate from all legacy projects:

```text
DB_HOST=127.0.0.1
DB_NAME=visawgnz_scholarsyncglobal
DB_USER=<dedicated database user>
DB_PASS=<dedicated database password>
SMTP_HOST=scholarsyncglobal.ca
SMTP_USERNAME=infos@scholarsyncglobal.ca
SMTP_PASSWORD=<mailbox password>
SMTP_FROM_EMAIL=infos@scholarsyncglobal.ca
SMTP_FROM_NAME=ScholarSync Global
```

Import `sql/scholarsyncglobal_schema.sql` and then
`sql/repair_scholarsync_missing_tables.sql` into the new database. Run the
remaining files in `sql/` as needed for optional modules.

## Deployment

The deployment helper uses SFTP and never stores credentials in the repository.
Set `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_PASSWORD`,
`DB_USER`, `DB_PASS`, and `SMTP_PASSWORD` in the deployment environment, then
run:

```text
py deploy/_ssh_deploy_scholarsyncglobal.py
```
