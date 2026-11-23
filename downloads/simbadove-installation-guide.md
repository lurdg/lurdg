# Namecheap Stellar installation

Deploy to an empty staging domain first. Do not delete or overwrite the existing WordPress site until the migration and cutover checks in MIGRATION.md are complete. The package runs independently of WordPress and WPCargo.

## Requirements and actual-account checks

- PHP 8.2+; use a currently supported version offered by the account. Local execution was tested with PHP 8.4.
- MySQL 8+ or MariaDB 10.6+, InnoDB and utf8mb4.
- PDO MySQL, mbstring, DOM/XML, fileinfo, GD, OpenSSL, sessions, filter/hash/JSON support.
- Apache/LiteSpeed rewrite support and the supplied public .htaccess.
- A local persistent private directory and writable storage; HTTPS certificate.
- Authenticated TLS SMTP for notifications and account email links. Shipment management and database intake continue if SMTP fails.

Check these in cPanel's PHP selector, phpMyAdmin, SSL controls, and hosting resource dashboard. Aim for 256 MB PHP memory for PDF/image processing. Individual uploads are limited to 5 MB and images to 8 megapixels; import CSVs are limited to 2 MB/1000 rows and processed in at most 50 valid-row chunks. Account CPU/I/O, entry-process, email, storage/inode and cron limits are hosting-specific; preflight cannot establish them. No continuous Node process, Docker, VPS, Redis, WebSocket server, WordPress plugin, or paid tracking API is needed.

## 1. Files and document root

Use File Manager to upload the ZIP outside public_html, extract it, and enable Show Hidden Files. Preserve bundled vendor/ dependencies. No Composer or SSH is required on the server.

### Configurable document root

Extract to `/home/ACCOUNT/simbadove-global`. Set the staging domain's document root to `/home/ACCOUNT/simbadove-global/public`. Private application files remain outside that document root. Keep its public .htaccess.

### Fixed public_html / primary domain

1. Extract the full private application to `/home/ACCOUNT/simbadove-global`.
2. Copy only the CONTENTS of its public/ folder to the staging/domain document root, commonly `/home/ACCOUNT/public_html`. Use the public/ .htaccess, not the root deny-all .htaccess.
3. In public index.php replace its bootstrap require with:
   ```php
   require '/home/ACCOUNT/simbadove-global/app/bootstrap.php';
   ```
4. In temporary public preflight.php set `$configFile` to `/home/ACCOUNT/simbadove-global/config/config.php` and its require to `/home/ACCOUNT/simbadove-global/app/Services/Preflight.php`.
5. Remove public router.php, which is only for the local PHP development server.
6. Keep app/, vendor/, resources/, routes/, database/, tests/, scripts/, storage/ and config/ private, never under public_html.

The application expects its own domain/subdomain root, not a path-prefix installation. Keep the original site backed up separately rather than merging WordPress files with the new public root.

## 2. Database

Use cPanel Database Wizard to create a database and dedicated user. Record the complete cPanel-prefixed names. Assign privileges on that database only. Select the empty database in phpMyAdmin and import database/schema.sql once. This includes initial schema, roles, status/method catalogs, factual business content and editorial drafts; it does not create accounts or shipments.

Initial import/migrations need schema privileges. Runtime uses SELECT/INSERT/UPDATE/DELETE; restrict runtime DDL privileges afterward if the account supports a separate migration identity. Do not reimport schema.sql into an existing installation. MySQL DDL auto-commits: diagnose partial imports before retrying, or recreate only the new empty staging database.

## 3. Private configuration and secrets

Copy config/config.example.php to config/config.php. Use environment production and the exact HTTPS origin without a path, query, username or fragment. A staging domain needs its own configured origin.

Generate an app_key of 64 cryptographically random hexadecimal characters and a separate random setup_token at least 32 characters long. A trusted password manager can generate both; locally, `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` creates one value at a time. Never reuse keys or leave REPLACE_ placeholders.

Fill the exact database host/port/name/user/password and SMTP settings. Escape a single quote/backslash inside PHP single-quoted password strings. Keep a secure separate backup of the app_key: it encrypts queued email bodies. SIMBA_CONFIG may optionally select a different private configuration path at server level; no dotenv parser is required.

SMTP: use your mailbox/provider's actual host and certificate hostname, username, password, authorized sender, and TLS mode. Port 465 normally uses `encryption => 'ssl'`; port 587 normally uses `'tls'` (STARTTLS). Certificate verification remains enabled. Configure the sender's SPF/DKIM and applicable DMARC. Production inbox delivery and quotas must be checked on the real account.

## 4. Permissions

Public directories 755 and public files 644, including CSS, JavaScript and images. The ZIP normalizes these public permissions to avoid 403 asset failures. Private config should be 600 or the tighter value compatible with your PHP handler; private directories should be accessible only as required by that account user. Never use 777. Ensure storage/logs, storage/sessions, storage/cache and storage/private are writable by PHP. New private uploads are stored with owner-only file permissions.

## 5. Preflight and administrator

Open `/preflight.php`, enter the private setup token, and resolve failed checks. Then open `/setup`. Create the first super administrator with a unique password of 14–72 bytes. Setup locks after creation. Sign in through `/login`; no default administrator credentials exist.

Delete public preflight.php after checking and blank setup_token in private configuration. Confirm setup is locked. Enable MFA on the hosting control panel. In-app MFA is not part of this release; use the implemented strong-password, throttling, session and permission controls and any additional organizational identity requirements before production.

## 6. Email operation

Default `mail_dispatch_on_request` is false. Choose one:

- Recommended: create an allowed cPanel Cron Job for the account's PHP binary running `/home/ACCOUNT/simbadove-global/scripts/mail.php` every five minutes (respect the host's minimum interval). Each run is bounded and prevents overlap.
- Without CLI/cron: set `mail_dispatch_on_request => true` in private config. Normal page requests dispatch at most one pending message within the configured timeout. The next page after a form redirect can perform delivery.
- Administrators can also use Notifications → Send pending messages manually.

Use Notifications → Send test to my account and check both the queue status and recipient inbox. SMTP failure does not discard business records. Failed messages retry with a 15-minute delay up to five attempts; an administrator can explicitly retry. Account links expire after 30 minutes, so request a fresh link if an old queue item arrives late.

## 7. Acceptance before cutover

- Test public pages and mobile navigation; verify CSS/images/token.js return 200, not 403.
- Verify /app, /config, /storage, /vendor and hidden files return 403/404 rather than source.
- Test customer registration, verification link, administrator approval, login/logout, reset/replay rejection and a second customer's access denial.
- Create a clearly fictional shipment, assign it, append public/internal events, verify chronology, upload authorized/private documents, and test tracking without login.
- Submit a quotation and contact enquiry; review/reply in administration and test real email delivery.
- Preview and confirm a small migration batch, including an invalid row and duplicate; verify both reports and associations.
- Test a backup restoration in a separate database, then follow MIGRATION.md for actual old data and cutover.

Do not treat a successful local test as proof of actual hosting configuration, migrated data accuracy, legal content approval or live mailbox reputation.
