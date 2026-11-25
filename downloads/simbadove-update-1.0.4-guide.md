# Update 1.0.4 — automatic location coordinates and visible map fallback

This cumulative update includes the previous record lifecycle, schedules, country-only
routes, map and public Departure summary. There is no new database migration beyond
003 and 004. An installation already running 1.0.3 needs only this code/assets update.

## Install on the live Namecheap application

1. Save a fresh database export and secure copies of simbadove-global and public_html.
2. If not already done, apply migration 003 then 004, once each, according to
   schema_migrations. Do not rerun completed migrations or import schema.sql.
   For an existing working 1.0.3 installation, skip this step.
3. Upload **simbadove-update-1.0.4.zip** into **/home/simbwdyc** and extract into that
   same directory, replacing its listed files. Application code goes into
   simbadove-global and public assets are also supplied under public_html/assets.
4. config.php, public index.php and .htaccess are excluded. Your working database,
   SMTP, canonical domain, absolute bootstrap path, redirects and cron remain intact.
   No API key, Composer, Node process or additional hosting service is required.
5. Refresh with Ctrl+F5 and sign out/in as administrator. Public layouts now use
   CSS v5 and tracking-map.js v2; staff layouts load the new geocode.js file.
6. Verify the behaviors below with fictional records, then remove the uploaded ZIP.
   Keep backups until satisfied. Use 644 public files / 755 directories, never 777.

## Generate coordinates from a typed place

### Origin and destination

Open a shipment's edit form. **Automatically find coordinates from public location
names when saving** is checked by default. With blank coordinates, saving looks up
Origin country / Destination country. Country names resolve to approximate country
centers, not a precise depot or pickup address.

For a more precise point, fill **Origin map place** or **Destination map place** with
a public facility or a city and country, then click **Find location**. This optional
map query does not restore the old required city fields. The matching place fills
latitude/longitude. Select the correct match if several are offered. Save the form.
Manual coordinates remain editable and take precedence over automatic generation.

### Current shipment location

On the shipment record, add a tracking update using the actual status/time and a
specific location such as **Freetown, Sierra Leone** or **Conakry, Guinea**. Keep
**Automatically find coordinates for this public location when saving** checked.
A lookup is also attempted after the completed location field changes; Find location
lets you preview/correct it. The lookup does not run on every keystroke.

When a unique match is available, latitude/longitude fill automatically and are stored
with that event. The latest public event's coordinates drive the public current pin.
Past event coordinates are not rewritten by editing the shipment endpoints: append
a truthful public update to publish the current position for an existing shipment.
No API requests are made just because a visitor opens public tracking.

For an ambiguous name, choose a match from the lookup list or add the country. If
no unique match is available, lookup is disabled/unavailable, or a limit is reached,
the shipment/update can still save without new coordinates and displays a notice.
Invalid manually entered coordinates still fail validation; they are not silently
replaced. Existing manual coordinates are retained unless you change the associated
place and request automatic lookup again.

Automatic lookup on save applies only to public updates. Confidential/private event
locations are not automatically submitted. Do not put customer names, personal home
addresses, internal notes or other confidential information into place queries.

## Map visibility fix

The public result now contains a server-rendered OpenStreetMap iframe fallback before
any local map JavaScript runs. It shows the available map area/current mapped point,
or a world view while coordinates are missing. The interactive Leaflet canvas is
revealed before initialization and replaces the fallback only after initialization, a visible map size, and a successful background tile load.
If the interactive library/initialization fails, the fallback remains; tile failures
can reveal it again. There is also an Open map separately link.

Only the interactive view draws the planned and recorded route lines. Fallback text
and the tracking timeline describe the route when that interactive view is unavailable.
Both depend on third-party map availability and browser support; a browser blocking
all external maps can still use the text timeline and separate-map link.

The map's current pin uses the latest public event only. An older geolocated event
is not relabelled as current if the newest public event lacks coordinates. Private,
deleted and archived records remain excluded. Departure (UTC) continues to replace
Created (UTC) in the public summary.

## Geocoding provider and limits

The default lookup service is **Photon**, using OpenStreetMap data:
https://photon.komoot.io — policy: https://github.com/komoot/photon#demo-server

The provider permits reasonable usage, may throttle/ban extensive use, and provides
no availability guarantee. This implementation is staff-only, caches successful
queries privately for seven days (empty results for 15 minutes), serializes requests
with at least 1.1 seconds between uncached calls, and defaults to at most 60 uncached
requests per hour across the application. The manual lookup endpoint is also
permission/CSRF protected and limited to 15 lookups per staff account per 15 minutes.
No bulk CSV, background polling, visitor-triggered, or cron geocoding is implemented.
No public Nominatim API is used. Maps and geocoding are not a live GPS feed.

An operator can add this OPTIONAL private configuration entry to use another
Photon-compatible HTTPS endpoint or disable automatic lookup globally:

```php
'geocoder' => [
    'enabled' => true,
    'endpoint' => 'https://photon.komoot.io/api/',
    'max_requests_per_hour' => 60,
],
```

Do not replace the rest of config.php. A larger deployment should use its own or a
suitable hosted geocoding provider, not rely on the public demo service. Endpoint
changes do not require replacing code. Only operator configuration can choose the
endpoint; a lookup request cannot choose an arbitrary URL.

The provider receives the submitted PUBLIC place name and server IP. Browser map
services receive visitor IP/viewed map area. Tile/embed URLs exclude shipment IDs,
references and customer details. The iframe uses no-referrer and a limited sandbox;
CSP allows only the specific HTTPS OpenStreetMap frame host and existing tile host.
No broad external-script or unsafe-inline permission was added. Review the public
privacy notice for these providers. Attribution remains visible beside/on the map.

## Verify and rollback

- Type a unique city/facility plus country, confirm generated coordinates and inspect
  the returned place label before saving. A place center may be approximate.
- Confirm manually entered values remain usable and ambiguous matches offer a choice.
- Add a public geolocated event and confirm its current marker/route. Add a private
  update and confirm it neither triggers automatic lookup nor changes the public pin.
- Confirm the iframe fallback is visible when the interactive map is blocked or
  unavailable. Check phone/desktop, attribution, Departure, WhatsApp and the timeline.
- Disable geocoding in private config temporarily if desired: record saving must
  still work with a clear lookup notice. Re-enable when finished.

Local checks passed: 30 main integration groups (original 14, lifecycle 8, map 4,
geocoding 4); two JavaScript behavior suites; PHP syntax; populated-schema upgrade;
and a live generic-city Photon request. The live fallback embed endpoint returned
HTTP 200. DOM/library and test-double checks are not browser rendering QA. The
assistant has not applied these files to your hosting account or changed that live
shipment. Verify rendering and outbound provider access on Namecheap after updating.

Rollback restores previous source/assets in both directories; no new SQL reversal is
needed for 1.0.3 to 1.0.4. Preserve matching DB/private-file backups when rolling back
older lifecycle changes. See earlier update guides for permanent deletion scope.
