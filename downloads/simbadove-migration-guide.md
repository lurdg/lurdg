# WordPress / WPCargo migration guide

## Preserve and investigate first

The public review found hidden unrelated SEO-spam links across the old site. Preserve a complete file/database snapshot, isolate it for review, and ask the hosting operator/security specialist to investigate the WordPress installation. Do not copy PHP, themes, plugins or opaque executable files into the replacement. The new platform has not modified the old site.

The public site does not expose the shipment database, complete histories, customer accounts or private documents. Actual migration cannot be verified until an authorized export is available. Retain the original website and a tested backup until reconciliation and cutover are approved.

## Inventory and authorized exports

Record the WordPress/WPCargo versions, shipment count, statuses, methods, custom fields, customers, historical updates, document counts, storage paths, existing URLs and ownership rules. WPCargo export capability may depend on the installed edition/add-ons. Use its available authorized export controls or a qualified operator's read-only database export; do not assume every installation uses the same column names or history format.

Export separately:

1. Customers: email, name, company, telephone, address and a separate old-ID-to-email crosswalk.
2. Shipments: original tracking reference; shipper/consignee contacts and addresses; countries/cities; cargo/method/weights/packages/dimensions; currency/value/insurance/cost/payment state; expected date; assigned customer email/staff email; original UTC creation time and internal reference/notes.
3. Tracking history: one row per historical event, including tracking number, status name, location, exact UTC date/time, description, explicit public visibility and unique source event key. Do not flatten a full history into only its current status.
4. Documents: original filenames, actual private files, related tracking number and access classification. Preserve event-specific associations in the reconciliation archive; the supplied document manifest imports shipment associations, not event IDs from a foreign database.
5. Website pages/posts: WordPress Tools → Export (WXR/XML), plus media originals and testimonial/history records. Preserve the original export unchanged.

Record source timezone and convert timestamps to UTC before importing. WordPress numeric author IDs are not customer IDs in the new system: resolve customers by the explicit email crosswalk. A shipper's email is not automatically the customer account.

## Formats and field mapping

Templates are in database/templates and downloadable from Admin → CSV migration. Import types: customers, shipments, events, documents and content. CSVs must be UTF-8, have unique named headers, contain at most 1000 data rows and be under 2 MB.

The automatic aliases in app/Services/Importer.php recognize common named WPCargo headers. A preview mapping JSON object can override them, e.g.:

```json
{"WPCargo Tracking Number":"tracking_number","Receiver Name":"consignee_name","Customer Email":"customer_email"}
```

No universal WPCargo schema is claimed. Unknown nonempty columns become row errors. Map or explicitly transform them in an auditable copy of the source export; keep the original data and transformation log. The importer does not deserialize PHP objects or run WordPress shortcodes.

Normalize method/status labels in the admin catalogs first. Country and city must remain distinct. Money uses decimal points, no currency symbols or thousands separators. Dimensions are a labeled text field with units. Status imports belong in events; shipment imports create an initial creation event and then preserve chronology as historical events are appended.

## Preview and confirm

1. Configure a staging installation, private storage, catalogs and operator accounts.
2. Import customers, then shipments, events and documents. Imported customers are pending/unverified and have unknowable random passwords. Legacy password hashes are never copied.
3. Upload CSV, select its type, provide any mapping, and inspect the preview. No business rows are written during preview.
4. Review each error, association and duplicate. Existing emails/tracking references are errors by default; explicit duplicate skipping records a skipped outcome rather than overwriting data.
5. Type IMPORT to process valid rows. Confirmation handles at most 50 valid rows/approximately 35 seconds per request; repeat confirmation while valid rows remain. Error rows remain recorded. No failed rows are silently discarded.
6. Retrying an already completed batch is rejected. Historical event source keys prevent adding the same event twice. Keep source exports and batch reports for reconciliation.
7. Imported historical shipments/events do not generate customer notifications. Send account password-setup invitations only after verifying associations, approval and SMTP readiness.

## Private document staging

Upload document originals through cPanel/SFTP into the configured migration_staging directory outside the document root. Fill documents.csv with tracking_number, relative_path, original_name, kind and explicit staff/customer visibility. Paths must stay inside that directory and cannot contain traversal, absolute paths, protocols or symlinks. Only validated PDF/JPEG/PNG files within the upload limit are imported; images are decoded/re-encoded. Remote URLs are not fetched.

The original file archive and manifest must be retained. Missing or rejected files are row errors. Verify downloaded files and customer authorization after import. Event-specific attachment relationships require an operator-reviewed mapping from old event IDs/source keys to the newly imported events; retain these in the original archive until mapped, rather than inventing associations.

## Website content and media

The supplied business-content.json and CONTENT-REVIEW.md preserve source facts and disputed claims. The operator explicitly requested that conflicting founding years/history, unsupported metrics and accreditation claims remain unpublished pending verification. Review testimonials and their usage permission before publication. Existing logo and selected raster photographs have provenance records; no executable WordPress content was reused.

An optional offline converter is provided:

```text
php scripts/migration/wxr-to-csv.php wordpress-export.xml NEW_OUTPUT_DIRECTORY
```

It uses DOM with external entities/network access disabled, converts supported page/post text into draft content.csv, produces a media-review manifest, and reports missing text/unsupported post types/builder shortcodes. It does not modify the source XML or fetch media. Run it on your own PC if the host has no terminal, or prepare equivalent CSV manually.

Upload the resulting reviewed content.csv via the Content import type. HTML and builder shortcodes require manual conversion to plain text. Existing duplicate slugs are reported; they are not overwritten automatically. Review source and current seeded business pages side by side, then use Website content to publish approved versions. Store downloaded media locally and assign the approved filename or upload through the editor.

## URL and data reconciliation

Preserve original tracking numbers wherever they pass validation; short or unusual references are reported for operator decisions. Public tracking also supports the original /track/ page, and /track-form/ routes to the same handler. /contact/ redirects to /contact-us, and /account/ opens sign-in. Public page slugs can be preserved through content import; maintain explicit redirects for any changed legacy article/service slugs before cutover.

Compare before/after counts by customer, shipment, status and year; sample full histories (not just latest status), declared values/currencies, addresses, ownership, filenames and downloaded file hashes. Review every rejected/skipped row and every unresolved document association. Exports intended for spreadsheet viewing prefix formula-like values for safety; account for those prefixes in any re-import transformation rather than altering source records silently.

## Cutover checklist

- [ ] Original WordPress files/database/documents backed up and source compromise investigated.
- [ ] Actual exports retained; mapping and timezone decisions recorded.
- [ ] Catalogs, customer crosswalks, roles and ownership approved.
- [ ] Preview errors/duplicates and unresolved fields reconciled explicitly.
- [ ] Shipment numbers, chronology, documents and customer counts reconciled.
- [ ] Email configured; verified customers receive fresh password setup links.
- [ ] User-approved company history/testimonials/certifications only are published.
- [ ] Privacy/terms/contact/SEO and changed-URL redirects reviewed.
- [ ] Security, responsive workflows and backup restore pass on staging.
- [ ] Freeze edits on the old system, export final delta, reconcile and approve cutover.
- [ ] Switch the document root/domain only after approval; monitor logs and email queue.
- [ ] Keep an isolated original backup and documented rollback route until retention review.

This ZIP supplies the tools and formats; it does not claim that real WordPress/WPCargo business data has already been transferred or fully reconciled.
