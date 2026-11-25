# Simba Dove Global Logistics — 1.0.2

Existing production installation: download simbadove-update-1.0.2.zip and follow simbadove-update-1.0.2-guide.md. Back up first and import 003_record_lifecycle.sql ONCE before replacing code. Do not import schema.sql into an existing database. The patch preserves configuration, public entry paths and redirects, and updates the fixed public_html stylesheet too.

Adds Trash/restore/permanent delete for customers, staff, quotes, enquiries, tracking updates, shipments and shipment documents; account protections and private-file cleanup; current public location; pickup/departure UTC dates; country-only routes. WhatsApp remains linked to +23274976543.

New installation: simbadove-global-logistics.zip includes the complete source, dependencies, latest schema, documentation and tests.

Validated locally: 14 original integration tests, eight lifecycle tests, foundation/content checks, old-schema upgrade, PHP syntax and a 27-table backup/restore. Browser QA for this update is not claimed. Apply acceptance checks from the update guide on hosting. No live production records have been modified by the assistant.

Patch SHA-256: `89df4d31bd7e83d85e18112151d7370b0a1743395909030914ce77157184cfe1`

Full ZIP SHA-256: `5d299b450cb08e580ba1756d8b5d91f8585ff75f0079b041876ba563acaadc73`
