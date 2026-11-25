# Staging verification

The redevelopment was exercised in an isolated PHP 8.4.27 and MariaDB 11.8.9 environment. The staged database contained synthetic fixtures. The production archive contains the installer and standard statuses and starts with an empty shipment database.

| Phase | Verified in staging | Remaining for cutover |
|---|---|---|
| 1. Audit | Reviewed the nine indexed pages, checked the post sitemap and extracted published contact, service and WhatsApp details. | Confirm the actual cPanel limits with the hosting provider. |
| 2. Architecture and data | Installed the schema twice. Checked 17 statuses, date and status indexes, and the unique lookup index. | Verify the live database version and PHP extensions on the separate staging subdomain. |
| 3. Interface design | Reviewed the homepage and service cards in Chrome. Checked the mobile menu and corrected horizontal overflow at a 388-pixel viewport. | Confirm behavior on actual iPhone and Android devices. |
| 4. Public website | Verified public routes and linked assets, the sitemap and legacy tracking redirect. Contact and quote submissions appeared in the dashboard. | Supply approved business policies and exact office coordinates if available. |
| 5. Shipment management | Exercised creation, editing, status history, assignment, search, filters, CSV, print views, attachment checks, archive, trash, restore and purge. | Run acceptance checks with staff accounts and approved test records on the host. |
| 6. Tracking and map | Lookups returned database records. Invalid and missing records showed an error. Checked masked names, verification codes, no-location handling, live map tiles and zoom. | Confirm provider behavior from the deployment domain. |
| 7. Dashboard and roles | Exercised the dashboard, agent assignment restrictions, staff and customer profiles, statuses, enquiries and audit records. | Assign real staff roles. |
| 8. Integrations | Verified the published WhatsApp URL. A local TLS SMTP receiver accepted shipment emails and a single-use password reset. Location search returned and cached a match. | Configure and verify the organization's SMTP provider, cPanel cron and optional geocoder. |
| 9. Security | Tested CSRF, session regeneration and revocation, unauthorized access, rate limits, literal rendering of markup, CSV formula handling and file access restrictions. | Verify HTTPS, host permissions and the private directory placement in cPanel. |
| 10. QA and performance | Ran 37 HTTP workflow assertions and checked 23 referenced local URLs. Ten local tracking requests had a median of about 9 ms. The database query used the unique tracking index. | Measure performance at the real host and on mobile networks. |
| 11. Migration | The normalized importer passed a no-write dry run, applied import, identical retry and tracking collision rejection with preserved events and an attachment. An additional dry run verified migration of a current status displayed separately from its history. | Confirm the WordPress timezone and reconcile clipped fields, remarks, coordinates and attachments with a complete source export. |
| 12. Packaging | Verified the archive contents and installed the extracted ZIP using the fixed document-root layout. First owner creation, setup locking, sign-in, assets and the empty shipment table passed. | Install in cPanel and complete the staging acceptance and restore drill before cutover. |

A local restore drill recovered indexed tracking lookups, history and attachment references and matched attachment checksums. Performance timings are local measurements. The legacy WordPress site was used only for public read-only checks. Any production deployment or migration needs the account owner to provide access or execute the documented staging and cutover process.
