# Update 1.0.5 — fresh asset filenames and compact location forms

No new SQL migration is added. An existing working 1.0.3/1.0.4 installation needs
only this code/assets update. This cumulative package retains the prior features.

## Install on the live Namecheap application

1. Save fresh database and file backups.
2. Upload **simbadove-update-1.0.5.zip** into **/home/simbwdyc**.
3. Extract into that SAME directory, allowing replacement. The ZIP merges code,
   a private asset manifest and generated public assets into simbadove-global,
   and copies assets into public_html/assets for the fixed document-root layout.
4. config.php, public index.php and .htaccess are excluded: database/SMTP settings,
   canonical URL, bootstrap path, www redirect, cron and existing data are preserved.
5. Refresh the public result with Ctrl+F5; sign out/in to see the compact staff forms.
6. Confirm map height, tiles, route/markers and form layout, then remove the uploaded ZIP.

Do not import schema.sql. If upgrading from BEFORE 1.0.2/1.0.3, only the still-missing
prerequisite migrations 003 and 004 must be applied first, once each. Otherwise no SQL.
Keep public files at 644 and directories at 755. Neither Python nor Composer is
needed on hosting: all generated files are already included.

## Why the map disappeared

A real browser received an older **Brotli-compressed** app.css response even though
an uncompressed HTTP request returned the updated stylesheet. The old compressed
response was 20,593 bytes after decompression and ended at the earlier lifecycle
styles; the current uncompressed file was 22,443 bytes at diagnosis and contained
the map rules. The browser therefore computed the interactive canvas height as zero,
and rendered the fallback iframe at its small default size. Version query strings
had not bypassed this server-side compressed copy. Earlier file-only checks missed it.

CSS and application/library scripts now use content-specific FILE NAMES, such as
app.<content-hash>.css, rather than relying on a query string against app.css.
A new body creates a new URL and compression-cache key. The private generated
app/asset-manifest.php maps original names to those files. Old original files remain
for compatibility. This applies to public/staff CSS, navigation, token, map, geocoding
and bundled Leaflet assets. Packaging also includes current build times and UTC timestamp metadata rather
than assigning the same fixed modification time to every release.

There is no need to delete hosting cache directories, disable Brotli globally, edit
.htaccess or change database records. Future developers should run
`python scripts/build-assets.py` locally after changing managed CSS/JS, then deploy
the updated manifest, views and generated files together. Python is a BUILD-TIME
helper only, not a production runtime requirement. Do not overwrite just the legacy
app.css and expect its old fingerprinted URL to update.

## Compact staff forms

- Shipment inputs are arranged in a narrower two-column layout on desktop and a
  single column on small screens. Most text/select controls are capped at 26rem.
- Origin and Destination map controls have separate compact cards with paired
  coordinate fields. Required city fields remain removed; countries are retained.
- Find location sits directly beside its map-place input on desktop and directly
  below it on small screens, with consistent button styling and a search icon.
- Event-location lookup is grouped with its own field and Find location button.
- Ambiguous-result selectors appear only when needed. Automatic lookup, manual
  coordinates, public/private handling and saved record behavior are unchanged.

## Validation

The affected live result was first reproduced in Chromium: map canvas clientHeight
was 0, despite loaded tiles and SVG shapes. A comparison using the current local
assets substituted ONLY into the test browser's responses showed the route/markers
correctly: desktop outer height 380px (clientHeight 378), mobile outer height 300px
(clientHeight 298), loaded tiles, and no JavaScript errors or failed requests. That
browser-only substitution did not change production files or shipment records.

Compact shipment/event forms were tested in an isolated local fixture at widths
1365, 390 and 320: no document overflow; desktop inputs measured at most 400/416px;
buttons were visually inspected beside their corresponding location fields. The
existing 30 main integration groups and both JavaScript behavior suites passed.

After installation, verify the live page requests the fingerprinted CSS/JS names,
the interactive map has nonzero height and shows the existing points/route, and the
fallback hides once map tiles load. Confirm mobile layout, automatic lookup, login,
Departure summary and WhatsApp. Coordinates do not need re-entering for this fix.

Rollback restores the matching earlier views, helper and assets from your backup;
no database reversal is needed for 1.0.4 to 1.0.5. Keep prior fingerprinted files while
old browser pages may still reference them. See earlier guides for provider limits
and the exact record-deletion scope.
