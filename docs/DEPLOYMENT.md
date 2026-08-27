# APRISM first inspection deployment

This package applies to Git commit `a873659` and tag `inspection-candidate-2026-08-27`.

## Hosting target

Use Apache or LiteSpeed PHP/MySQL hosting with PHP 8.2+, `pdo_mysql`, ZIP/XML/GD extensions required by PhpSpreadsheet, Composer or SSH access, HTTPS, and a writable private storage location. A cPanel/LiteSpeed shared host is sufficient for the professor-inspection release. Use the host's temporary HTTPS URL now; attach `aprism.tech` or `app.aprism.tech` later by changing only `app_url` in `config/environment.php`.

## First deployment

1. Create an empty production database and least-privilege database user. Do not import local test data or a local full SQL dump.
2. Upload the committed source release, then run `composer install --no-dev --prefer-dist --optimize-autoloader` on the server. If SSH/Composer is unavailable, upload the vendor directory created from that exact `composer.lock` as part of the private production release, never to public Git.
3. Copy `config/environment.example.php` to `config/environment.php`; set production database credentials, `app_url`, storage path, error-log path, and the host-confirmed `mysqldump_path` (or an empty value when unavailable).
4. Create writable `storage/backups`, `storage/class_list_sources`, and `storage/logs`; prefer a path outside `public_html`. When storage remains under the application root, retain both supplied `.htaccess` protections.
5. Apply `database/01_authentication.sql` through `database/13_students.sql` in numeric order to the empty database. Then run `php database/migrate.php` once from SSH/Terminal. The migrations create the nullable-SCE and AprilTag schema required by this release.
6. Enable the host's SSL certificate and force HTTPS at the hosting/domain layer. In the production domain's PHP settings (cPanel MultiPHP INI Editor or the host equivalent), set `session.cookie_secure = On`; do not put that setting in the shared repository because local XAMPP currently uses HTTP. Do not launch camera features without HTTPS.
7. Check `https://your-host/health.php`; it must return `{"status":"ok","release":"inspection-candidate-2026-08-27"}`. Then test login, each role's enabled navigation, My Classes, a read-only Class List, and AprilTag page loading.
8. Create the production Technical Administrator through the approved bootstrap/initial database account flow, force a password change, and remove or disable any test/demo accounts before sharing the URL.

## Release and rollback

For each later release: test locally; commit and tag; back up production via the host/cPanel database backup; enable maintenance for migration or compatibility-sensitive releases with `php maintenance.php on`; deploy one complete release directory; run new migrations; check health and essential pages; disable maintenance with `php maintenance.php off`.

For code-only rollback, repoint the host to the previous complete release directory/version. For a failed migration, restore the pre-release production database backup or run a pre-tested reverse migration. Never overwrite production data with a local SQL dump.

The in-app backup button is supplementary only. The host-managed database backup is the release backup authority unless the host explicitly confirms that PHP may execute the configured `mysqldump` binary.
