# SIMBA DOVE update 1.0.7

For existing 1.0.5 or 1.0.6 installations. No database migration is required.

## Changes

- Content-width Move to Trash and Resend password setup buttons, icons, spacing and a grouped account action area.
- Public tracking shows sender and receiver names and postal addresses, as approved, plus shipment type, total weight in kg and total packages.
- Dedicated phone/email fields, financials, private documents, internal notes and private tracking events remain hidden. Postal addresses display as entered: keep phone/email values in their separate contact fields.
- Existing map, current-position pulse and reduced-motion support are preserved.

## Install on Namecheap

1. Save a current files/database backup.
2. Upload simbadove-update-1.0.7.zip to /home/simbwdyc in cPanel File Manager.
3. Extract into /home/simbwdyc, allowing replacement of included files. Its two folders merge into simbadove-global and public_html.
4. Refresh the browser with Ctrl+F5. Check customer/staff action buttons and a public tracking result on desktop and mobile.

This package updates app/Helpers/Core.php, app/asset-manifest.php, resources/views/users.php, resources/views/shipments.php, resources/views/track.php, matching CSS/map assets in both public asset folders, and release documentation. Deploy the manifest and matching assets together.

It contains no production config.php, public index.php, .htaccess or SQL. Do not replace these files or change credentials. Existing postal addresses become visible on public tracking after installation; review their contents before sharing tracking links.

Rollback: restore the replaced files from your backup together, including the asset manifest and assets. The database is unchanged.
