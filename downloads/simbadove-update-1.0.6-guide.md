# Update 1.0.6 — content-width buttons, close search controls and pulsing position

This is a small styling update for the already installed 1.0.5 application. It
changes CSS, the map display script and the private asset manifest, not business logic or records.

## Install

1. Take a file backup of simbadove-global and public_html (or retain your current
   production backup and download the files listed in this package's manifest).
2. Upload **simbadove-update-1.0.6.zip** into **/home/simbwdyc**.
3. Extract into that SAME directory, allowing replacement. CSS and the map display script are supplied under both simbadove-global/public/assets
   and public_html/assets, together with the matching private app/asset-manifest.php
   and update documentation.
4. Refresh with Ctrl+F5. Check the public forms and administrator search/action forms.
5. Remove the uploaded ZIP after confirming the layout.

No SQL, credential/configuration changes, index.php replacement, .htaccess changes,
cron changes, or re-entry of shipment locations are needed. The working map and
automatic location lookup are preserved. The small update assumes 1.0.5 is installed;
apply the prior 1.0.5 cumulative update first if you are on an older release.

## Button and search changes

- Buttons and button-style links use their label/icon's natural content width,
  bounded by available space. Long labels can wrap rather than overflow.
- Shared padding is .65rem vertically / 1rem horizontally, with a 44px minimum
  touch height. Existing specific icon/lookup controls retain their appropriate size.
- Stacked forms no longer stretch submit buttons across the full form. Action groups
  use a consistent .65rem gap; submit controls have a little separation from fields.
- Filter wrappers have bounded widths instead of stretching far beyond their actual
  input. Search buttons sit about 8px from the preceding field on desktop.
- On narrow screens, search/lookup buttons fit their content and can move directly
  below their input with a small gap, rather than stretching full width.
- Public tracking searches, homepage tracking, staff filters, record actions and
  ordinary form buttons share the same sizing behavior.

The new stylesheet has a content-specific filename, so the hosting server cannot
reuse the old Brotli CSS cache under the previous URL. Deploy the asset manifest and
its matching generated CSS together. Legacy app.css is included for source consistency.
Do not delete old fingerprinted assets while an older page might still reference them.

## Pulsing current position

Only the latest published current position has an expanding/fading halo; the center
marker remains fixed at the saved coordinates. Origin, destination and historical
checkpoints do not pulse. The effect is visual only: it does not imply a GPS feed
or trigger background API requests. Browsers with reduced motion enabled show a
static ring instead. The external fallback map's own marker remains static.

## Validation and rollback

45 read-only local Chromium page/viewport combinations passed: 15 public/staff pages
at 1365px, 390px and 320px. Checks measured buttons against label/icon/padding width,
verified no document overflow, and measured search/lookup separation. Ordinary search
gaps were 8px; lookup controls remained about 10.4px from their corresponding inputs,
including when stacked on mobile. Representative desktop/mobile searches and a
stacked import form were visually inspected. No production forms were submitted. A separate read-only browser check with fresh local CSS/JS substituted into the test browser confirmed changing halo opacity, a fixed center and disabled animation under reduced-motion preference; no page errors occurred.

Rollback restores the previous private asset manifest and stylesheets from your file
backup. No database restore or migration reversal is needed for this styling change.
