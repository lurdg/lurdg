# Deployment and data design

The deployment has a public document root with a single PHP entry point and static assets. A sibling private directory holds source, SQL, credentials, file storage and command-line utilities. A separate database and document root isolate staging from the legacy WordPress site.

Shipment status, its dated event and any notification are written in one InnoDB transaction. Status-changing actions serialize on the shipment row. History records separately preserve the time of the event and the time it was recorded. Location updates retain their coordinates and location name. The public map displays the latest recorded coordinate by event time.

The public tracking route accepts a tracking number as a POST field. The lookup key uses a keyed digest and a unique index. The public response uses an explicit allowlist. The separate verification code unlocks full party names only. Staff accounts use password hashes, expiring reset links, and session revocation on account changes.

The role hierarchy scopes permissions by operation. Assignment checks apply independently whenever an agent reads or updates a shipment. Archive keeps tracking available. Trash hides tracking and is restorable. A permanent purge of a trashed shipment requires an owner and leaves an audit record.

A bounded cPanel cron job delivers queued email over authenticated TLS. SMTP and geocoding are configurable. Public lookups never make server-side requests to a geocoder. The database uses UTC and the interface displays a configurable timezone.
