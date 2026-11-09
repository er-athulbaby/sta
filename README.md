# Sports Entry Management System

Laravel 12 + Filament 4. `/admin` = Super Admin and STA users. `/portal` = association admins and editors.

## Local setup
1. PHP 8.5 with `intl`, `gd`, `sodium`, `zip` and `pdo_sqlite` (and `pdo_mysql` for MySQL). On Windows, use Herd's `php85` binary.
2. `composer install && cp .env.example .env && php artisan key:generate`
3. Set DB_* (SQLite is fine locally) and `SUPER_ADMIN_NAME` / `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` in `.env` (read via `config('app.super_admin')`).
4. `php artisan migrate --seed` creates permissions and the super admin. `SUPER_ADMIN_PASSWORD` is blank in `.env.example`; locally a blank value falls back to `change-me-now`. In production the seeder refuses a blank password or `change-me-now`.
5. `php artisan serve` and `php artisan queue:work` (invitation emails are queued).
6. Tests: `./vendor/bin/pest`

Excel import/export uses openspout (no Laravel Excel). Admin panel users can optionally enable authenticator-app MFA at `/admin/profile`.

## Production notes
- `APP_NAME="<organisation portal name>"` (used in emails and page titles), `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
- `MAIL_FROM_ADDRESS=<sender address>` and `MAIL_FROM_NAME="${APP_NAME}"`.
- Set a strong, unique `SUPER_ADMIN_PASSWORD` before the first `migrate --seed` (it is blank by default and the seeder refuses blank or `change-me-now`). Change it after the first login, and enable MFA at `/admin/profile`.
- `PRIVATE_DISK=s3`, `AWS_DEFAULT_REGION=me-south-1`, `AWS_BUCKET=<private bucket>`; the bucket must block public access.
- `MAIL_MAILER=ses`, `QUEUE_CONNECTION=database` with Supervisor running `queue:work`; cron runs `schedule:run`.
- PDFs: dompdf (pure PHP), no extra server packages. Queue worker must run (`queue:work`) for PDFs and emails.
- Passport reader: install Tesseract OCR and poppler on the server (`sudo dnf install -y tesseract poppler-utils`, or `apt install tesseract-ocr poppler-utils`). Optional `.env`: `TESSERACT_PATH`, `PDFTOPPM_PATH`. Locally on Windows, install Tesseract (UB Mannheim build) and poppler and set both paths. Tests do not need them.
- Never rotate `APP_KEY` without re-encrypting people documents and recomputing `passport_no_hash` / `cpr_no_hash`.

Design: `docs/superpowers/specs/2026-11-04-sports-entry-system-design.md`, scope: `docs/Sports-Entry-System-Scope-and-Design.docx`.
