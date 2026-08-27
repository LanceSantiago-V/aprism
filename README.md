# APRISM deployment preparation package

Apply these files to the frozen source baseline `a873659` / `inspection-candidate-2026-08-27`, then commit a new production-preparation checkpoint before deploying.

Replacements: `config/app.php`, `config/database.php`, `.gitignore`, `index.php`, `actions/system/download_backup.php`, and `includes/navigation/sidebar_items.php`.

New files: `.user.ini`, `.htaccess`, `config/environment.example.php`, `errors/`, `health.php`, `maintenance.php`, `storage/` protection files, `database/migrate.php`, and `database/migrations/`.

Do not add `config/environment.php` to Git. Follow `docs/DEPLOYMENT.md` in this package.
