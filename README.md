# Afrilink Global Logistics

Standalone PHP and MariaDB website and shipment-management application for Namecheap Stellar shared hosting. The public site uses the contact details and services published by Afrilink. An empty database is installed with a status catalogue and an administrator you create.

## Download

Get the cPanel-ready ZIP from the [Afrilink Global v1.0.0 release](https://github.com/lurdg/lurdg/releases/tag/afrilink-global-v1.0.0). The release ZIP includes minified browser assets.

## Contents

- public/ contains the web entry point, rewrite rules, images, styles, fonts and Leaflet.
- private/app/ contains the website, staff dashboard, shipment and notification code.
- private/schema/ contains the installation SQL.
- private/bin/ contains the mail worker and historical data importer.
- private/config.example.php lists the private deployment settings.
- docs/ contains the installation, staff, migration, backup and verification guides.

Start with **docs/INSTALL-NAMECHEAP.md**. Keep the private directory and database credentials out of the document root. The ZIP is self-contained and runs with regular PHP requests. The bundled dependencies require no server-side build step.

Hosting-account verification and the actual legacy WPCargo field mapping are required before cutover. Deploy to an isolated HTTPS staging subdomain first.
