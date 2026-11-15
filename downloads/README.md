# CVM — Namecheap Stellar deployment

[Download the complete deployment ZIP](https://github.com/lurdg/lurdg/raw/refs/heads/cvm-namecheap-stellar/downloads/cvm-namecheap-stellar.zip)

The ZIP contains the PHP/MySQL application, bundled PHP dependencies, SQL schema, admin panel, MFA, permit approvals/revocation, QR and PDF generation, installation guides, and screenshots.

Extract the ZIP and read `cvm-namecheap-stellar/docs/INSTALL.md` before uploading to cPanel. Only the contents of `public/` belong in the website document root; keep configuration, source, dependencies, and storage private.

No production configuration, authentic permits, accounts, sessions, backups, or test-generated secrets are included. MCAS is not connected. Run the included preflight on the actual Namecheap account.

Validated locally: ten integration tests, twenty viewport checks, SMTP STARTTLS, Apache routing, and backup restoration. See `docs/VALIDATION.md` inside the ZIP for test boundaries.

Package SHA-256: `cf8b9ffa7b7f3e8b8871ded96991d62dcd85011be2b3b98676f5f2792b21aa88`
