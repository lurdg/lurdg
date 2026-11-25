# Simba Dove Global Logistics — 1.0.6

Existing 1.0.5 installation: download the small simbadove-update-1.0.6.zip and follow simbadove-update-1.0.6-guide.md. No new SQL or configuration changes. Older installs should apply the cumulative 1.0.5 update first. Back up before replacement.

Buttons fit their labels/icons with consistent padding and margins. Search inputs have bounded wrappers and buttons remain about 8px away instead of being pushed far across the page. Mobile buttons fit their content and can stack immediately below their fields. A pulsing halo highlights the latest public map position while its center stays fixed; reduced-motion preferences disable the animation.

The small patch updates matching CSS, map JavaScript and the private asset manifest, with both source-public and live-public_html copies. It preserves database records, configuration, entry paths and redirects. Existing locations need no changes.

Read-only Chromium checks passed 45 page/viewport combinations at 1365/390/320 pixels, with button width, input/button gap and overflow assertions. A browser-only fresh-asset substitution check confirmed animated halo opacity, a fixed center and reduced-motion support. The assistant has not installed this update on hosting.

The complete simbadove-global-logistics.zip is also refreshed for new installations and contains the full current source, schema, dependencies, tests and documentation. Do not use the fresh-install schema as an upgrade.

Patch SHA-256: `4187c6e42be865b5be9c5dc1d491a7292af9d323d0a7cb27ca0858d16e9d771b`

Full ZIP SHA-256: `4a08a0ba7657558d8081d634bc88efcbe1ef5d8d6ca9ec4af11c88dabc8da2b1`
