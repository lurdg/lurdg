# Backup and restore on Stellar

In cPanel, create a database export and back up the public and private application files, including configuration and private documents. Use cPanel backups or phpMyAdmin for the database. Keep copies off the hosting account in encrypted, access-controlled storage. Include the private application key, the database export and documents from the same backup window. The application key is required for tracking lookups and verification codes. Store the SMTP and database credentials securely with the operational handoff.

For a consistent copy, pause application changes and cron while taking the database and file snapshots or use a host-supported point-in-time snapshot. Document the date, software versions, database name, and archive and SQL checksums. Keep at least one tested restore point taken before deployment or migration. Namecheap's full self-service AutoBackup interface is documented for Stellar Plus and Stellar Business. Take your own backups on base Stellar and confirm with support if you need assistance restoring a full-account archive. [Namecheap cPanel backup guide](https://www.namecheap.com/support/knowledgebase/article/9365/2199/how-to-create-and-restore-backups-in-cpanel/)

## Restore drill

1. Set up a separate HTTPS staging subdomain and create a new database and restricted database user.
2. Recreate the public and private directory layout, including hidden files and the storage root. Restore the database into the new database. Keep the original application key and update the database connection and base URL in the private config.
3. Disable SMTP delivery to real contacts during the drill. Clear old staging sessions. Keep the restored environment access-controlled because it contains customer details.
4. Verify a staff account, a known tracking number, shipment history, attachment downloads, and the dashboard counts. Record how long the restore took and retain check totals.
5. For a live restore, pause writes, run the same reconciliation, then switch the document root or application database at the agreed cutover. Keep a copy of the failed state and reconcile post-backup changes before reopening writes.
