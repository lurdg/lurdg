# WPCargo migration and cutover

## Inspect the actual source

The public tracking page loaded WPCargo assets with version **8.0.6** at audit time. The active plugin, its add-ons, the actual field mappings and number of shipments require inspection on the account. This package includes an importer for a reviewed normalized format. It depends on a mapping derived from the actual export. The public page does not establish the source database layout.

1. Record the active WordPress, WPCargo and add-on versions and the site timezone. Preserve an off-host copy of the site files, database and available access logs. Review the source environment before reusing material from the account.
2. Restore a working copy in an isolated environment with access restricted. Identify the shipment record type, shipment fields and add-ons, status definitions, history format, timezone and attachment storage. Record source counts and sample formats. Exclude credentials and personal details from material shared for mapping. Retain the full backup in controlled storage.
3. If the installed WPCargo add-on supports export, export all shipment fields and history. If the export omits fields, use a read-only extraction from the isolated copy based on the actual table names and field types. Compare both sources where practical. Preserve tracking numbers as strings so leading zeros survive. Map each source status to an existing code and preserve its original historical label.
4. Review attachments individually. Import intended, safe shipment files only. Verify MIME type, size, SHA-256 and shipment association. Choose a local folder containing reviewed PDFs, JPEGs and PNGs. Keep other attachment types in a restricted legacy archive for manual resolution.

[WPCargo export documentation](https://www.wpcargo.com/knowledgebase/how-to-import-and-export-through-wp-admin/)

## Normalized import format

Use UTF-8 JSON Lines with one object per line and at most 500 records per batch. Each line may be up to 1 MB. The importer accepts up to 2,000 events and 100 attachments per shipment. Use strings for identifiers and numeric values. Every record requires the following values.

| Field | Expected value |
|---|---|
| source_key | Stable unique key from the source, such as a namespaced record ID |
| tracking_number | Original tracking number as text |
| created_at | Original shipment creation time in RFC 3339 with a timezone offset |
| status_code | Mapped current status code from the new platform's status table |

Additional shipment keys include **reference**, **shipper_name**, **shipper_phone**, **shipper_email**, **shipper_address**, receiver equivalents, **origin**, **destination**, **description**, **category**, **packages**, **weight** in kg, **dimensions**, **method**, **pickup_at**, **departure_at**, **estimated_delivery**, **updated_at**, **status_at**, **internal_notes** and **public_notes**. Use RFC 3339 timestamps with offsets for date-times and **YYYY-MM-DD** for the estimated delivery date. Leave unknown source values empty. Only map notes into **public_notes** after reviewing what customers may see.

The optional **events** array contains **kind** (status or location) and **occurred_at**, with optional **recorded_at**, **location**, **latitude**, **longitude**, **description**, **customer_remarks** and **actor_name**. Status events also take **status_code** and optional **status_label** for the original historical wording. Keep tied timestamps in source order, oldest entry first. For location events, supply both coordinates. A text-only location can be retained in its source status event. Retrieve saved coordinates from the source when available. A screenshot of a map pin provides a visual reference and does not establish exact coordinates.

When WPCargo shows a current status that differs from the latest history row, verify the standalone status against the source and map it to the intended catalogue code. Set **current_status_snapshot** to the JSON boolean **true** to preserve that status alongside the original history. Provide **status_at** only if the original event time is known. The importer otherwise displays when it captured the status and labels the original time as unavailable. A mismatch without the explicit flag stops the batch for review.

Confirm the WordPress timezone under **Settings → General** and whether the plugin uses it for tracking history. Normalize the time text before applying offsets. A time such as **16:00 pm** already uses a 24-hour hour value. A departure time without a date is insufficient to construct a stored departure timestamp. Keep cropped input values and remarks pending source verification.

The optional **attachments** array contains **path** relative to the reviewed files directory, **sha256**, and optional **name**. Use one stable input format and retain authenticated custody of the source files and mappings. Imported shipments keep their original tracking numbers. Public lookup uses case-insensitive matching and shows party names only with a new verification code for legacy records. Case or whitespace collisions require manual reconciliation.

## Dry run, apply and verify

From the new application's private directory on staging, use the PHP CLI selected for the install.

~~~sh
php bin/import-history.php --file=/private/reviewed/shipments.jsonl --attachments=/private/reviewed/documents
php bin/import-history.php --file=/private/reviewed/shipments.jsonl --attachments=/private/reviewed/documents --apply
~~~

Without **--apply**, the database transaction is rolled back and files are validated without copying. An exact retry of a committed batch skips identical source rows. A changed source row or tracking collision stops the batch. Use the line number to reconcile the source, then retry. Email notifications are not queued during the import.

Reconcile all source shipment, history and attachment totals. Verify orders, statuses, dates and attachment checksums. Test representative tracking numbers, including numbers with leading zeros. Keep exceptions and sign-off with the migration records. Issue secondary verification codes only through approved customer contact channels.

## Cutover and rollback

Test restoration before scheduling a cutover. Back up both systems, confirm host clean-up and credential rotation where applicable, and set a short change freeze to avoid concurrent shipment writes across old and new systems. Export and apply the reviewed final batch, then reconcile the inventory. Switch the document root or deploy into the live root only after the verification passes and the owner approves the window. Set the live base URL and recheck SSL, URLs, tracking, SMTP and the cron path. Preserve mail and DNS settings unless a confirmed hosting change requires edits.

Keep **/track-form/** redirecting to **/track/**. Validate the existing **/my-account/** users before retiring or mapping that route. Confirm which spam URLs should return **410 Gone** and use a reviewed list with Search Console. Preserve a recoverable copy of the old system outside the public root. If checks fail, retreat to the agreed safe serving state and reconcile any writes before switching databases again. A potentially compromised WordPress backup should remain isolated until reviewed.
