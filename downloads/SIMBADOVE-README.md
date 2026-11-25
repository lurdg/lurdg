# Simba Dove Global Logistics — 1.0.3

Existing installation: download the cumulative simbadove-update-1.0.3.zip and follow simbadove-update-1.0.3-guide.md. Take backups; check schema_migrations and apply 003_record_lifecycle.sql then 004_tracking_map.sql only when not already applied. Never import the fresh-install schema into production.

Adds a public Leaflet/OpenStreetMap map, explicit endpoint/event coordinates, planned connection, recorded public checkpoints and latest public position. Private/deleted coordinates are excluded; unknown locations are not guessed. Departure (UTC) replaces Created in the public tracking summary. Includes the earlier Trash/delete/restore, schedule and country-only route changes.

Patch preserves private configuration, entry paths and redirects; public assets are supplied for both the source public/ directory and the actual public_html root. Leaflet is bundled with its license. No API key, GPS feed, automatic geocoding or road-route service is required/claimed.

New installation: simbadove-global-logistics.zip includes the current complete source, schema, dependencies, tests and documentation.

Validated locally: 26 main integration groups, foundation/content checks, JavaScript behavior tests with lightweight test doubles, syntax, populated-schema upgrade and 27-table backup/restore. Browser/rendering QA for this update is not claimed. Apply hosting acceptance checks from the guide. The assistant has not changed production records or deployed this update to hosting.

Patch SHA-256: `5fb9665cb174d8ab39a39b908bc0a498461f55d59dfdb70e21830754d8af086a`

Full ZIP SHA-256: `c1e2ec76c3c13e59d1a9de0d7a491bcff93142ebf97bc0f01ea78dac31bb2bda`
