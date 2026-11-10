# RUNBOOK — The Harmonic System

How to run, deploy, and back up the app on InfinityFree. The server has no shell, no Composer, no cron, and no Node.

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate     # set DB_* for local MySQL
php artisan migrate --seed                            # demo: owner@example.com / harmonic123 (Super Admin)
./tailwindcss -i resources/css/app.css -o public/css/app.css --watch
php artisan serve
composer test                                         # Pest; must pass before any upload
```

Tests use the MySQL database `harmonic_test` (see `phpunit.xml`). The Tailwind standalone CLI (`tailwindcss` / `tailwindcss.exe`) is downloaded from the Tailwind releases page and is not committed.

## Build a release

1. `composer test` — every test passes.
2. `./tailwindcss -i resources/css/app.css -o public/css/app.css --minify`
3. `composer install --no-dev --optimize-autoloader`
4. Check no PHP file is over 1 MB: `find . -name "*.php" -size +1M -not -path "./node_modules/*"` prints nothing.
5. Zip everything except `.env`, `tests/`, `storage/logs/*`, `storage/framework/{cache,sessions,views}/*`, `tailwindcss*`.

## First deploy

1. In the InfinityFree panel create the MySQL database; note host, name, user, password.
2. Upload the release into `htdocs/` by FTP. The root `.htaccess` rewrites every request to `public/` and refuses `.env`, `composer.*`, `storage/`, `vendor/`, `database/`, `tests/`.
3. Upload a production `.env` with:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, a fresh `APP_KEY` (generate locally with `php artisan key:generate --show`)
   - `DB_CONNECTION=mysql` and the panel's DB values
   - `SESSION_DRIVER=file`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` (once HTTPS works), `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`
   - `SUPER_ADMIN_EMAIL=you@example.com`
4. Make `storage/` and `bootstrap/cache/` writable (FTP client: 755 for folders).
5. First Super Admin: either run `php artisan app:make-super-admin you@example.com` locally against the production database before upload, or sign up / be invited with the `SUPER_ADMIN_EMAIL` address — the account is flagged on its next sign-in.
6. Open `/admin/maintenance` and press **Run migrations**.

## Every later deploy

1. Build a release (above).
2. Upload changed files by FTP. Never overwrite `.env` or `storage/`.
3. `/admin/maintenance` → **Run migrations**, then **Clear caches**.
4. Sign in and open Home, Metrics Dashboard, and a Weekly Meeting to confirm.

## Scheduled work

There is no cron. The lazy scheduler runs due jobs after an ordinary page request, at most once every 10 minutes per organization: closing Alignment rounds and Diagnostic cycles, opening and closing 360 cycles, topping up repeating events 26 weeks ahead, and purging organizations 30 days after deletion. `/admin/maintenance` shows the last run of each job.

## No email in v1

The app sends no email. Invitation links and password reset links are shown once to the admin who creates them (People page, or the Super Admin console for orgs without an admin); the admin passes them on. Reset links last 60 minutes, invitations 7 days.

## Backup

Weekly, and before every deploy:

1. InfinityFree panel → phpMyAdmin → select the database → **Export** → Quick, SQL → save as `harmonic-YYYY-MM-DD.sql`.
2. Keep the production `.env` in a password manager (the `APP_KEY` decrypts sessions; losing it signs everyone out but loses no data).
3. Keep the last 8 backups off the server.

## Restore (rehearse once before launch)

1. Create an empty database (locally: `CREATE DATABASE harmonic_restore`).
2. Import: phpMyAdmin → **Import** the `.sql` file, or locally `mysql -u root harmonic_restore < harmonic-YYYY-MM-DD.sql`.
3. Point `DB_DATABASE` at it, open `/admin/maintenance`: pending migrations should be 0. Sign in and check an organization's Home and Metrics Dashboard.
4. On production, restore into the live database only after taking a fresh export of its current state.

## Limits to remember

- 50,000 hits per day: only a live Weekly Meeting polls (every 15 s, paused in hidden tabs).
- Lists paginate at 25. Keep organizations under about 20 users on the free plan (PRD D3).
- No file uploads; procedures and tasks use links.
