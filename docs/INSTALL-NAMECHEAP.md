# Install on Namecheap Stellar

## Confirm the hosting account

In cPanel, open **Select PHP Version** and select a maintained PHP 8.4 release or a later supported branch verified in staging. Enable PDO MySQL and confirm ctype, fileinfo, mbstring, OpenSSL and session support. Enable cURL for location lookup. Set the upload limit to at least 8 MB and the POST limit above it if staff will upload documents. Verify the database version in phpMyAdmin and review cPanel **Resource Usage** for actual CPU, memory, I/O and entry-process limits. The application was exercised locally on PHP 8.4 and MariaDB 11.8. The hosting account remains a separate acceptance environment.

Create an HTTPS staging subdomain with its own document root, database and database user. Restrict access with cPanel Directory Privacy or another access control while preparing the site. Test rewrite rules and SSL on the subdomain. Keep the existing WordPress application and database online until the cutover steps in the migration guide are complete.

Namecheap references are available in its guides on [PHP selection](https://www.namecheap.com/support/knowledgebase/article.aspx/9417/2219/how-to-change-php-version-and-update-php-extensions-on-shared-servers/), [shared server software](https://www.namecheap.com/support/knowledgebase/article.aspx/129/22/what-version-of-the-software-is-used-on-your-servers/) and [resource limits](https://www.namecheap.com/support/knowledgebase/article.aspx/1127/103/a-handy-guide-to-resource-limits-or-what-is-lve/).

## Extract and configure

1. In File Manager, turn on **Show Hidden Files**. Upload the ZIP outside the public document root and extract it there.
2. Arrange the files so the document root points to **public/**. A common staging layout is **/home/ACCOUNT/afrilink-stage/public** with its sibling **/home/ACCOUNT/afrilink-stage/private**. For a fixed **public_html** document root, copy the contents of **public/** into the correct document root, including **.htaccess**, and place **private/** beside that document root under the name **afrilink-private**. The front controller checks for **afrilink-private/** first and **private/** second. Check that public folders have permissions **0755** and public files have **0644** after extraction or copying.
3. Open **Manage My Databases** or **MySQL Databases**. Create a dedicated database and a new database user with a strong password. Add that user to the database and grant privileges to create the schema during setup. Use the full cPanel-prefixed names. For routine operation, a separate user with SELECT, INSERT, UPDATE and DELETE on the application's database is sufficient. Keep the setup credentials for future schema changes in private storage.
4. Copy **config.example.php** to **config.php** in the private directory. Set **base_url** to the HTTPS origin with no subdirectory. Set the database host, port, name, user and password. Namecheap typically uses **localhost** for the local database. Confirm the host in cPanel. Keep **allow_http** false.
5. Generate the **app_key** and **setup_key** independently. From a trusted PHP CLI, run **php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'** once for each value. A password manager that can generate 64 random hexadecimal characters is also suitable. Save the private config with permissions **0600** when the PHP handler runs as your account. The app key is part of the backup set and is needed to look up existing tracking numbers. Both keys are secrets.
6. Enable the hosting HTTPS redirect and check that the site and static assets are served over HTTPS. Confirm URLs such as **/about/** and **/setup/** route through the application. Use an isolated subdomain for tests and keep its DNS, SSL and document root distinct from the live site.
7. Visit **/setup/** over HTTPS. Enter the setup key, your name, an admin email you control, and a unique password between 12 and 72 bytes. This creates the database tables, seeds the status catalogue and creates the first super administrator. Sign in at **/admin/**. Confirm another visit to **/setup/** returns 404. Clear **setup_key** in the private config after successful setup.
8. Open **Settings** and verify the phone, email, address, public profile and WhatsApp number. Set the display timezone and tracking prefix for new records. The settings page also accepts approved policies, company history, office hours and coordinates. Publish an office map pin only after confirming the actual coordinates.

## WhatsApp

The default number is the international number published by Afrilink, **23272603254**. Edit **WhatsApp international digits** in **Settings** without spaces or the leading plus sign. The optional greeting is URL-encoded. Open the link on a mobile device and a desktop to confirm it targets the intended business account. The widget opens a conversation. Shipment email delivery uses the SMTP configuration below.

## SMTP and cron

Edit the private **smtp** configuration with the sending host, port, encryption, username, password and verified sender address. Use **tls** for STARTTLS or **ssl** for implicit TLS according to your provider. Namecheap documents ports 587 and 465 for its hosted mail. Confirm that your chosen sender and provider support authenticated mail from the account and that their SPF and DKIM records are in place. The customer service email in Settings receives enquiry copies. The SMTP **from_email** may differ when the provider authorizes a separate sender.

In cPanel Cron Jobs, configure one entry at five-minute intervals. Confirm the actual CLI PHP binary with Namecheap. An example is

~~~sh
*/5 * * * * /opt/alt/php84/usr/bin/php /home/ACCOUNT/afrilink-private/bin/send-mail.php
~~~

The CLI path and private application directory must match your account. The worker also clears expired rate-limit and reset records. Enable **Email notifications** under Settings to send shipment notifications after SMTP is functioning. Contact and quote enquiries stay in the dashboard even when SMTP is disabled. Staff password resets require working SMTP. Use a staging inbox you control to verify contact and quote notifications, shipment notifications and a password-reset link. Inspect the queue and cron logs if delivery fails. A failed item is retried and recorded without logging the recipient or body to cron output.

[Namecheap SMTP settings](https://www.namecheap.com/support/knowledgebase/article.aspx/9142/2186/general-configuration-for-mail-clients-and-mobile-devices/), [Namecheap cron limits](https://www.namecheap.com/support/knowledgebase/article.aspx/1212/22/do-you-allow-cron-jobs/)

## Maps and location search

Shipment maps use Leaflet and OpenStreetMap tiles. The tracking page shows the latest dated location and includes a map when that update has coordinates. The map provider receives tile requests when a customer opens the map. Staff record the location on the same form as the status. Use public location labels for logistics facilities and add accurate privacy wording for the deployment's data handling and map provider.

To activate **Find coordinates**, set `geocoder_url` to an HTTPS Nominatim-compatible search endpoint and `geocoder_contact` to a business email in private `config.php`. The public Nominatim search endpoint, `https://nominatim.openstreetmap.org/search`, can be used for low-volume lookups of public locations within its usage policy. Enter public place names such as cities, airports or freight terminals when using that provider. The application caches results and limits outbound lookup frequency. Staff can also enter coordinates manually. [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/) · [OpenStreetMap tile policy](https://operations.osmfoundation.org/policies/tiles/)

If a background lookup fails, **Find coordinates** runs the search through the shipment form and keeps the entered fields intact. The tracking update is saved when staff select **Save tracking update**. If the form reports that location lookup is unavailable, check the PHP error log for the provider HTTP status and cURL error number.

## Verify the install

Test staff sign-in, shipment creation, a tracking update with status and location, public tracking on phone and desktop, a contact and quote submission, authorized CSV export and print output. Check the approved WhatsApp link, HTTPS, private file access, cron, SMTP and password reset. Verify backups restore on staging. Review **MIGRATION-WPCARGO.md** before enabling live traffic.
