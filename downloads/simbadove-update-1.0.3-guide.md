# Update 1.0.3 — public route map and departure summary

This cumulative code update includes 1.0.2's Trash/deletion, UTC schedule and
country-only route changes, plus the map and public departure-summary changes.

## Install on the existing Namecheap production application

1. Back up the production database **simbwdyc_simba_stage**, private application
   **/home/simbwdyc/simbadove-global**, and **/home/simbwdyc/public_html**.
2. If phpMyAdmin still reports access denied for a **cpses_...** user, close its
   tabs, sign out of cPanel, sign back in through Namecheap, and open phpMyAdmin
   from that session. If that fails, contact Namecheap for cPanel/phpMyAdmin
   session authentication. Do not change working application database credentials
   to match the temporary cPanel user. Resolve access before running migrations.
3. In phpMyAdmin select **simbwdyc_simba_stage** and inspect **schema_migrations**:
   - If **003_record_lifecycle** is absent and it has never been partially run,
     import the supplied **003_record_lifecycle.sql** once.
   - Then, if **004_tracking_map** is absent, import **004_tracking_map.sql** once.
   - Skip migrations already listed. If an import fails, stop and retain the
     exact error; DDL can partially commit, so do not blindly rerun it.
   - Do not import schema.sql into this existing database.
4. Upload **simbadove-update-1.0.3.zip** into **/home/simbwdyc**, then extract into
   that same directory, allowing replacement. It merges application files into
   simbadove-global and copies CSS, map JavaScript and bundled Leaflet assets to
   public_html/assets as required by the fixed public_html deployment.
5. The package excludes config.php, public index.php and .htaccess. Your canonical
   live URL, database/SMTP settings, absolute bootstrap path, www redirect and cron
   remain unchanged. Keep public files at 644 and directories at 755; never 777.
6. Refresh with Ctrl+F5 and sign out/in as administrator. Verify the checks below
   using fictional records. Remove the uploaded ZIP from the server afterward.

Run SQL before deploying code. Apply during a quiet period and avoid concurrent
record edits during replacement. This is the existing-install update package;
the complete application ZIP is for a fresh installation. For a domain served
straight from simbadove-global/public, install only the simbadove-global entries.

## Enter the route and position

No API key is required. Leaflet 1.9.4 is bundled locally; OpenStreetMap supplies
background tiles through the visitor's browser. There is no automatic geocoder,
GPS feed, carrier integration or road-route API.

1. Edit/create a shipment. Under **Public map route (optional)** enter the origin
   and destination latitude/longitude. Country fields remain; city fields stay removed.
2. To find coordinates, open https://www.openstreetmap.org, search for the terminal
   or facility, and center the map on the location approved for publication. Its
   URL ends in **#map=ZOOM/LATITUDE/LONGITUDE**. Copy latitude and longitude into
   the matching fields. Use up to seven decimal places, no degree symbols, and
   a minus sign for west/south. Supply both coordinates or leave both blank.
3. When appending a tracking update, enter the event location and its optional
   latitude/longitude. Check **Show this update and its coordinates in public
   tracking** only for information approved for public disclosure.
4. Newly created shipments copy configured origin coordinates into their initial
   public event. Editing endpoints on an existing shipment does not rewrite its
   past event coordinates: add a new public location update to publish its position.

Latitude must be between -90 and 90; longitude between -180 and 180. Missing or
invalid coordinates are never replaced by an invented pin. Existing shipments
receive null coordinate values during migration. Their text tracking still works.

## What the public map shows

- Origin and destination markers at explicitly entered endpoint coordinates.
- A dashed blue planned connection between origin and destination.
- Pink lines connecting recorded, geolocated public checkpoints chronologically.
- A green marker for the latest public update only when that exact update has
  coordinates. If the latest public update lacks coordinates, old checkpoints
  remain visible but none is mislabelled as the current position.
- No markers or lines derived from private or deleted events. Shipment soft deletion
  disables public tracking, including the map. Event deletion/restore recalculates
  which public position is current.
- The map uses up to the latest 500 public updates for performance; a notice appears
  if truncated. The full tracking timeline remains available below it.

Lines connect entered points. They are not surveyed travel paths, turn-by-turn
road routes, or a live GPS feed. On shipments with no published coordinates, the
map shows a world view with an explicit coordinates-pending notice. If the library
or tile service is unavailable, tracking details and history remain usable.

**Departure (UTC)** replaces **Created (UTC)** in the public tracking summary.
It uses the shipment's departure_at value; missing values display **Not provided**.
Creation timestamps remain in the database/admin/history and are not substituted
for a missing departure date.

## Tiles, security and privacy

Leaflet JavaScript/CSS load from your own assets. Only HTTPS images from
https://tile.openstreetmap.org are added to the Content Security Policy; external
scripts, arbitrary tile hosts and a broad unsafe-inline policy are not enabled.
Popup labels are DOM text, not raw HTML. Public map data contains only whitelisted
coordinates, location labels and public event times, not customer identity,
financials, internal notes or private events.

The tile provider receives the visitor IP and map tile area. Tile URLs do not
include a shipment reference or customer data; the existing strict-origin referrer
policy limits cross-origin Referer to your origin. An inline disclosure appears
beside the map; review your approved privacy notice for this third-party service.
OpenStreetMap attribution remains visible. Standard OSM tiles have usage limits
and no production SLA; obey https://operations.osmfoundation.org/policies/tiles/.
No bulk tile downloads, prefetch or public geocoding requests are implemented.
Use a suitable tile provider if traffic or service requirements exceed those limits.

## Verify after updating

- Create a fictional shipment with endpoint coordinates and a departure time.
  Public tracking should show Departure (UTC), origin/destination markers and the
  planned connection. Confirm Created (UTC) is absent from the summary.
- Add a public geolocated event: its location should become the green current marker.
- Add a later private geolocated event: neither its location nor its coordinates
  should appear in the public map or its data attribute.
- Add a later public event without coordinates: the current text should update,
  while the map explicitly says the current location has no published coordinates.
- Delete/restore a public event through Trash and confirm map/current location follow
  the remaining history. Soft-delete the shipment and confirm public tracking is 404.
- Confirm a missing departure date displays Not provided. Check mobile layout,
  zoom/pan, popup text, attribution and WhatsApp. Tracking text must remain usable
  if JavaScript or the tile service cannot load.
- Also follow UPDATE-1.0.2.md's lifecycle acceptance checks if that update was not
  installed before this cumulative update.

## Validation and rollback

Local PHP 8.4/MariaDB checks passed: original functional/lifecycle suites, four map
integration groups, JavaScript behavior checks using a lightweight Leaflet/DOM test
double, syntax, and a populated old-schema upgrade through migrations 003 and 004.
JavaScript checks cover route layers, date-line handling, zero coordinates, missing
current positions, safe popup text and unavailable-library/tile/JSON behavior.
These are not browser rendering or tile-provider availability tests. Browser QA was
unavailable for this update; verify desktop/mobile map rendering on your hosting.

To roll back code, restore the matching previous files in both locations. The
additive coordinate columns can remain. Restore matching database/private-file
backups as needed if using lifecycle features changed records. Permanent deletion
has no in-app undo; see UPDATE-1.0.2.md for its exact scope.
