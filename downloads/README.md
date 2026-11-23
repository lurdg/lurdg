# CVM — Namecheap Stellar deployment

## Existing installation: notice-removal update

[Download the small update ZIP](https://github.com/lurdg/lurdg/raw/refs/heads/cvm-namecheap-stellar/downloads/cvm-notice-update.zip)

Extract the update and follow `cvm-notice-update/APPLY-UPDATE.txt`. Copy the contents of its `application/` folder into your existing private application directory, merging folders and replacing matching files. For the current deployment that directory is `/home/reveahqa/cvm-namecheap-stellar/`. No configuration or database changes are required. Your existing public_html routing and asset-permission fixes are preserved.

The requested status notice has been removed from four templates, the README, and disabled-adapter CLI output. Screenshots were refreshed. This presentation update does not enable a source connection.

## Fresh installation

[Download the complete deployment ZIP](https://github.com/lurdg/lurdg/raw/refs/heads/cvm-namecheap-stellar/downloads/cvm-namecheap-stellar.zip)

The ZIP includes PHP/MySQL source, bundled dependencies, schema, admin panel, MFA, approval/revocation, QR/PDF downloads, setup guides and screenshots. Read `docs/INSTALL.md`. Only public/ belongs under the document root; keep application/configuration/storage private. No live credentials, accounts or permit records are included.

## Verification

Five modified PHP files passed syntax checks. Twenty existing viewport checks and four administrator screens passed; password-reset browser behavior also passed. Archives were checked for integrity and exclusion of runtime secrets. See `docs/VALIDATION.md` for the original full-suite results and update scope.

SHA-256:

- Full ZIP: `78518e5481afeb68e002ff87afd74511f273fca4c7fae47ea93c256f0d318c29`
- Update ZIP: `cac1cd6b0fd936b96fe794bb1006b5b75bbf6f8b4d3b2ac285f78e89f834514d`
