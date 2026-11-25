# Simba Dove Global Logistics — 1.0.5

Existing installation: use cumulative simbadove-update-1.0.5.zip and simbadove-update-1.0.5-guide.md. A working 1.0.3/1.0.4 installation needs no additional SQL. Earlier installs need only still-missing migrations 003 and 004, once each. Back up before extraction; never reimport the fresh-install schema into production.

Fixes stale compressed browser assets using content-specific CSS/JS filenames and a private generated manifest. Prior browser checks reproduced a zero-height map caused by an old Brotli CSS response despite a newer uncompressed file. Fresh assets substituted only in the test browser rendered the map/route/markers correctly at desktop 380px and mobile 300px, without modifying production.

Adds compact staff shipment/event forms, grouped Origin/Destination map controls and Find location buttons directly beside their inputs on desktop or below on mobile. Forms passed overflow checks at 1365/390/320 pixels. The prior automatic geocoding, fallback, Departure summary, record lifecycle and WhatsApp remain included.

Patch preserves configuration, public index.php and .htaccess and supplies assets in both source public/ and live public_html locations. Matching manifest and fingerprinted files must deploy together. The build helper is local-only; no Python/Node process is needed on Namecheap.

Full simbadove-global-logistics.zip is for a fresh installation and includes all source/dependencies/schema/tests/guides. Existing users should apply the smaller update ZIP.

Validation: 30 main integration groups, two JavaScript suites, syntax, database upgrade/restore and desktop/mobile browser checks. Production files and shipment records have not been changed by the assistant.

Patch SHA-256: `efc88c93b41e4ca3167e60d2b99cab023d282a83161a704d63afa3276b8d1df0`

Full ZIP SHA-256: `160fd305edb9b4b6e6c98772344145cbb08971c0113a7206ef809acf3663db89`
