APRISM release and deployment runbook

This is the verified runbook for the temporary InfinityFree inspection deployment.

Live URL: https://aprism-inspection.infy.click
Web root: htdocs
Production database: if0_42767070_aprism on sql305.infinityfree.com

Permanent rules

Develop and test in local XAMPP. Do not edit normal application PHP files live.

Commit and tag every release. The Git release must not contain vendor/, config/environment.php, runtime storage/ data, logs, uploads, backups, or SQL exports.

The private InfinityFree upload ZIP is different: it includes the matching locally-built vendor/ directory, but never config/environment.php or storage/.

The live database is authoritative. Never replace it with a local dump, bootstrap, schema-continuation, or demo-seed SQL file.

Use the direct HTTPS URL above; do not use www..

Production account unlock

user_security_status holds temporary lock state; it is separate from users.account_status.

For an unlocked active account, the expected state is:

users.account_status = Active
failed_login_attempts = 0
last_failed_login_at = NULL
locked_until = NULL

Only for the confirmed account that is locked, inspect it in production phpMyAdmin:

SELECT u.user_id, u.username, u.email, u.account_status,
s.failed_login_attempts, s.last_failed_login_at, s.locked_until
FROM users AS u
LEFT JOIN user_security_status AS s ON s.user_id = u.user_id
WHERE u.username = 'REPLACE_WITH_USERNAME';

Then unlock only its security state:

UPDATE user_security_status AS s
JOIN users AS u ON u.user_id = s.user_id
SET s.failed_login_attempts = 0,
s.last_failed_login_at = NULL,
s.locked_until = NULL,
s.updated_at = CURRENT_TIMESTAMP
WHERE u.username = 'REPLACE_WITH_USERNAME'
AND u.account_status = 'Active';

Do not change the role, password, email, or create a new administrator merely to unlock an existing active account. Never put a password in a screenshot, Git, or this document.

Every normal code-only update

After the local browser test, run this from the VS Code terminal in C:\xampp\htdocs\aprism:

$release = 'release-YYYY-MM-DD-r1'
$php = 'C:\xampp\php\php.exe'

git status --short
git diff --check
Get-ChildItem -Recurse -Filter \*.php -File | ForEach-Object {
& $php -l $_.FullName
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

composer install --no-dev --prefer-dist --optimize-autoloader
git add -A
git commit -m "Describe the completed release"
git tag -a $release -m "APRISM $release"
git push origin HEAD
git push origin $release

Build the private upload ZIP. It includes vendor/ but does not commit it:

$desktop = [Environment]::GetFolderPath('Desktop')
$stage = Join-Path $desktop "APRISM_INFINITYFREE_$release"
$bundle = Join-Path $desktop "APRISM_INFINITYFREE_$release`\_WITH_VENDOR.zip"

if ((Test-Path $stage) -or (Test-Path $bundle)) { throw 'Use a new release label; output already exists.' }
New-Item -ItemType Directory -Path $stage | Out-Null
git archive $release | tar.exe -xf - -C $stage
Copy-Item -Recurse -Force .\vendor (Join-Path $stage 'vendor')
tar.exe -a -c -f $bundle -C $stage .
Get-Item $bundle | Select-Object FullName, Length

In InfinityFree File Manager, always use this short safe sequence because File Manager extraction is not atomic:

Keep the last known-good WITH_VENDOR.zip privately on your computer.

Create htdocs/storage/maintenance.flag containing any short text.

Upload and extract the new WITH_VENDOR.zip into htdocs.

Replace the application folders and root files as a batch, including vendor/.

Preserve htdocs/config/environment.php and the whole htdocs/storage/ directory. Do not overwrite or delete them.

Delete intentionally removed obsolete code files while maintenance is still enabled.

Delete htdocs/storage/maintenance.flag.

Run the live smoke test below.

Update with a database migration

Use the same sequence, with these additions:

In InfinityFree phpMyAdmin, export/download the production database first.

Enable the maintenance flag before any code or database change.

Upload the matching code + vendor/ ZIP.

In phpMyAdmin, import only each new database/migrations/YYYYMMDD_description.sql file, one at a time.

Check whether the migration itself records its name. If it does not, record it once:

INSERT INTO schema_migrations (migration_name)
VALUES ('YYYYMMDD_description.sql');

Complete the smoke test, then remove the maintenance flag.

Never re-run the first deployment bootstrap, schema-continuation, or synthetic inspection-demo seed. Migrations are forward schema changes only; they never replace production Students, classes, tags, attendance, accounts, or other data.

Rollback

If a release fails, keep maintenance enabled.

Re-upload/extract the previous known-good WITH_VENDOR.zip into htdocs.

Preserve config/environment.php and storage/ exactly as they are.

If a migration was applied, restore the production database export made immediately before that release.

Test health.php and login. Remove the maintenance flag only after both pass.

Keep the failed ZIP and database export until the restored release is verified.

Minimum live smoke test

https://aprism-inspection.infy.click/health.php -> {"status":"ok", ...}
https://aprism-inspection.infy.click/ -> login works
Teacher Dashboard -> opens
My Classes -> opens
Class List -> opens
AprilTags -> opens
Fake URL -> APRISM controlled 404

Verified first deployment

The first inspection deployment used InfinityFree PHP 8.3, MySQL host sql305.infinityfree.com, .htaccess, HTTPS, and writable htdocs/storage/. Composer dependencies were built locally and uploaded in a vendor-inclusive ZIP. The production schema and synthetic inspection demo seed were imported successfully in phpMyAdmin. Health, login, My Classes, Class List, and AprilTags were verified live.

Local XAMPP and InfinityFree are separate environments: XAMPP uses the local database and can run Composer locally; InfinityFree has no SSH or server-side Composer, runs database imports through phpMyAdmin, and keeps protected runtime storage inside htdocs/storage/.

=============================================
I updated APRISM locally. Follow this frozen release/deployment procedure and guide me one action at a time through deploying the new version safely.
