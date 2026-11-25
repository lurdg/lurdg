# Simba Dove Global Logistics — 1.0.4

Existing installation: use cumulative simbadove-update-1.0.4.zip and simbadove-update-1.0.4-guide.md. No new SQL beyond migrations 003 and 004; an existing working 1.0.3 installation needs only code/assets replacement. Older installations must apply each missing prerequisite once. Never reimport the fresh-install schema into production.

Adds staff-triggered Photon place-name coordinate lookup, private caching, bounded requests, ambiguity selection and manual values; private updates/CSV/public views do not trigger automatic lookup. No API key is required. Photon public-demo reasonable-use limits and lack of SLA are documented in the guide; configure a suitable Photon-compatible HTTPS endpoint for higher traffic.

Adds an initially visible server-rendered OSM fallback; the interactive map replaces it only after initialization, visible dimensions and a successful tile load. Map URLs contain no shipment reference/customer identity. Departure (UTC), lifecycle controls, schedules, country-only routes and WhatsApp remain included.

Public configuration, entry paths and redirects are preserved. Assets are supplied in both source public/ and live public_html paths. For a new install, the full simbadove-global-logistics.zip contains source, dependencies, schema, tests and guides.

Validated locally: 30 main integration groups, two JavaScript behavior suites, PHP syntax, populated-schema upgrade, 27-table backup/restore, real generic-city Photon lookup and fallback endpoint HTTP 200. Browser rendering/Namecheap outbound access must be checked after installation. The assistant has not deployed the update or changed the live shipment.

Patch SHA-256: `703bf7913b0975671600e03600fa14bd3ea5db7ddebe2be66f891cb60aa59674`

Full ZIP SHA-256: `378ea97f141a162755569a15e8efc577bfb54a70c9c75edb9ce8500542d62c24`
