# EMC operations and data care

## Backups

Run `scripts/backup.ps1` every day from a restricted server account. It creates one ZIP containing a transaction-consistent MySQL dump, private order photos, metadata, and a separate SHA-256 checksum. Its Windows default is the service account's local application-data directory, outside this web project; pass `-BackupRoot` to select the approved encrypted off-site/synchronised destination. For password-protected MySQL, pass a locked-down MySQL option file with `-DefaultsExtraFile`; do not put the password in a scheduled-task command.

Keep backups outside the web root on encrypted storage. A practical starting schedule is seven daily, five weekly, and twelve monthly copies, but EMC and the hosting provider must approve the final schedule. Alert when a backup fails or its size unexpectedly drops. Verify the checksum after every transfer and perform a restoration drill into an isolated database at least monthly.

Restoration order:

1. Stop order intake or enable hosting maintenance mode.
2. Verify the ZIP against its `.sha256` file.
3. Restore `database.sql` into an empty isolated database first and validate table/order counts.
4. Restore `order-photos` to the private storage directory without making it publicly readable.
5. Point staging at the restored database and complete the customer/admin smoke test.
6. Only then restore production, restart traffic, and record who approved it.

Backups contain names, phones, addresses, notes, password hashes, and shoe photos. Treat them as private customer data and delete expired backup copies securely.

## Photo retention

Order history is retained independently from photo files. The cleanup command only targets photos belonging to `done` or `cancelled` orders older than the selected number of days; it keeps the order and its status history.

Always preview first:

```text
php api/cli/purge-order-photos.php --days=180
```

After checking the counts and taking a successful backup:

```text
php api/cli/purge-order-photos.php --days=180 --execute
```

The minimum is 30 days. Schedule execution only after EMC publishes and approves the same period in its privacy wording. Set `EMC_ORDER_PHOTO_RETENTION_DAYS` so scheduled runs do not depend on an undocumented value. Investigate any non-zero `failures` result.

## Routine administration

- Keep exactly one active administrator unless EMC explicitly changes the role model.
- Review submitted orders daily and move statuses only through the choices offered by the app.
- Write at least one clear English or Myanmar note for every change; both are preferred.
- Keep package prices fixed for existing orders. Editing a package affects future orders only.
- Set pickup fee to `0` when pickup is free. The fee applies only to pickup orders.
- Hide obsolete packages instead of altering old order records.
- Reset the admin password from the server CLI if access is lost.

## Monitoring and incidents

Monitor HTTPS expiry, `/api/health`, HTTP 5xx rates, free disk space, MySQL availability, backup completion, and the writable photo directory. Never log request bodies, passwords, cookies, CSRF tokens, customer addresses, or photo contents.

For suspected account or server compromise: take the site offline, preserve logs, rotate the app key and database/admin credentials, invalidate both session tables, inspect database/photo changes, restore only from a known-good backup, and notify affected customers according to local requirements.
