# Update 1.0.2 — record deletion and shipment fields

Prepared for the existing Simba Dove installation on Namecheap, including its fixed
public_html arrangement. This is an update to apply to the live account yourself;
the assistant has not modified the hosting account or its production database.

## Included changes

- Move customers, staff, quotes, enquiries, tracking updates, shipments and shipment
  documents to Trash; restore them or permanently delete them from Trash.
- Public tracking shows Current location and its recorded UTC timestamp, drawn
  only from the latest public, non-deleted tracking event.
- Shipment create/edit forms have optional Pick up and Departure date/time fields
  in UTC. Departure cannot precede pickup when both are supplied. The values are
  included in shipment details, public tracking, PDFs and CSV records.
- Origin city and Destination city are removed from forms and route display.
  Origin country and Destination country remain. Old city data is retained in the
  database; legacy CSV columns remain accepted but are omitted from new templates.
- The WhatsApp button remains linked to +23274976543.

## Install through cPanel

1. Take a fresh SQL export of **simbwdyc_simba_stage** and secure copies of
   **/home/simbwdyc/simbadove-global** and **/home/simbwdyc/public_html**.
   This database is production despite its staging name. Keep backups off the server.
2. Download the separate **003_record_lifecycle.sql** provided with this release.
3. In phpMyAdmin, select **simbwdyc_simba_stage**. Under schema_migrations, confirm
   **003_record_lifecycle** is not already listed. Import **003_record_lifecycle.sql
   once**. It adds columns, permissions and a private-file cleanup queue while
   preserving existing data. Do not import schema.sql or the earlier migrations.
   If an error occurs, stop and record it; DDL auto-commits, so do not blindly retry
   a partially applied migration. The database user needs ALTER/CREATE privileges.
4. Upload **simbadove-update-1.0.2.zip** to **/home/simbwdyc**.
5. Extract into **/home/simbwdyc**, replacing the listed application files. The ZIP
   merges into **simbadove-global/** and updates only **public_html/assets/css/app.css**
   in the public root. It does not contain config.php, public index.php or .htaccess;
   your live canonical URL, database/SMTP settings, absolute bootstrap path and
   www redirect are preserved. The manifest lists all included files and hashes.
6. Keep public CSS at 644 and directories at 755. Do not use 777. Refresh with
   Ctrl+F5 and sign out/in as administrator. Updated layouts request app.css?v=3.
7. Verify each check below using fictional records, then delete the uploaded ZIP.
   The SQL file inside the project is a reference, not an automatic web installer.

Import the SQL before copying the new code. Apply during a quiet period and keep
users from editing records during file replacement. No credential, application-key,
mail-server or cron-path changes are needed. The existing mail cron continues.

If you instead use a custom document root pointing directly at simbadove-global/public,
only the simbadove-global entries are needed; do not create an unrelated public_html.
The full application ZIP is for fresh installs and is not the production update ZIP.

## Use deletion and recovery

Active lists and shipment records show **Move to Trash** to authorized staff.
**Trash and recovery** appears in the administrator menu. Select a record type,
then Restore or Permanently delete. Both operations require a reason. Permanent
deletion additionally requires typing **DELETE ID**, for example **DELETE 42**.
Only records already in Trash can be permanently deleted. Existing archived
shipments are treated as soft-deleted and appear under Shipments in Trash.

The new permissions **records.delete** and **records.purge** are granted to Admin
and Super Administrator by default, alongside the existing permission for each
record type. A super administrator can assign them to additional staff roles.
Customers cannot access these actions. A signed-in account cannot delete itself;
the final active super administrator and super-administrator accounts are protected.
Account deletion invalidates sessions and outstanding password/verification links.
Restoring an account preserves its previous approval/verification state, but does
not reactivate an old session or used email link.

Restore a parent shipment before its individually trashed updates or documents,
and an update before its individually trashed attachment. A parent's soft deletion
blocks downloads but does not overwrite each child's independent Trash state.

## Permanent deletion scope

| Record | Related data removed or retained |
| --- | --- |
| Customer/staff | Account profile and auth tokens removed; independent shipments, quotes, enquiries, replies, imports and audit history retained with account references cleared. Historical staff names display Former staff. |
| Shipment | Tracking events and all its document records/files removed; independent enquiries retained with shipment association cleared. |
| Tracking update | Event and its attachments removed; shipment status, delivery timestamp and public location recalculated from remaining events. |
| Quote | Quote, its replies and quote attachments removed. |
| Enquiry | Enquiry and its replies removed. |
| Shipment document | Document record and its private file removed. |

Private filenames are queued transactionally and physically removed after database
commit. If deletion fails or more than 100 files are pending, Trash shows a cleanup
notice. Use **Retry private file cleanup** until the queue is empty. Downloads are
already blocked when the database records are removed. This avoids deleting files
before a transaction that might roll back. Physical removal depends on filesystem
permissions; failures remain visible and retryable. Audit records, sent email logs,
previously delivered email, backups and exported copies are not erased. This is not
a claim of secure disk erasure or automatic removal from external recipients.

## Acceptance checks

- Create a fictional shipment without city fields and with pickup/departure UTC times.
  Edit and reopen it, download the PDF and export CSV; confirm values persist.
- Add a public event in location A, then a private event in location B. Public
  Current location must remain A. Add a later public event in C; it must become C.
- Move the latest public event to Trash: location/status must fall back to the
  previous public event. Restore it and confirm both recover. With no public
  events, tracking says No public updates and Not yet recorded.
- Upload a customer-visible test PDF. Soft-delete it and confirm its download
  returns 404. Restore it and download. Permanently delete it from Trash and
  confirm the row/file and any cleanup-queue entry are gone.
- Exercise soft-delete, restore and permanent deletion with a fictional quote,
  enquiry, customer and staff account. Confirm deleted accounts cannot sign in.
- Archive/soft-delete a fictional shipment: public tracking and downloads must
  return 404. Restore it, then soft-delete and purge it; children must disappear.
- Check non-admin staff/customer sessions cannot use delete or purge actions.
- Check public and customer pages on phone and desktop, WhatsApp, login, email
  and cron. Keep actual business records until these checks pass.

## Validation and rollback

Validated locally on PHP 8.4/MariaDB against disposable fictional records: fresh
installation, original functional suite, lifecycle/authorization/cascade tests,
private-file cleanup failure/retry, upgrade from the previous populated schema,
syntax checks and database backup/restore. Browser verification for this update
is not claimed; existing screenshots are from the earlier release.

Keep code and database backups together. To roll back before using new features,
restore the previous code; the additive columns can remain. After using deletion
or scheduling, restore the matching database and private-file backup as well if
you need the earlier state, accounting for legitimate records created since backup.
Permanent deletion has no in-app undo. Do not drop columns manually to roll back.
