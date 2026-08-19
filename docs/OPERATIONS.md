# EMC operations and data care

## Backups

For local Windows/XAMPP recovery drills, run `scripts/backup.ps1` from a restricted account. It creates one ZIP containing a transaction-consistent MySQL dump, private order photos, metadata, and a separate SHA-256 checksum. Pass `-BackupRoot` for an approved encrypted destination and a locked-down MySQL option file with `-DefaultsExtraFile`; never put a password in a scheduled-task command.

On Hostinger, use the hosting account's supported database/file backup facilities and confirm that both the configured EMC database and `storage/app/private/order-photos` are included. Download or replicate backups to encrypted off-account storage; a backup stored only on the same hosting account is not sufficient disaster recovery. Record the exact hPanel/cron procedure, retention, last successful run, and restore evidence in `docs/ACCEPTANCE_TEST.md`.

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
php artisan emc:purge-order-photos --days=180 --dry-run
```

After checking the counts and taking a successful backup:

```text
php artisan emc:purge-order-photos --days=180
```

The minimum is 30 days. Schedule execution only after EMC publishes and approves the same period in its privacy wording. Set `EMC_ORDER_PHOTO_RETENTION_DAYS` so scheduled runs do not depend on an undocumented value. Investigate any non-zero `failures` result.

For Hostinger cron, use the deployed absolute path and verify the CLI PHP version first, for example:

```text
php -v
php /home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare/artisan emc:purge-order-photos --days=180
```

## Routine administration

- Keep exactly one active administrator unless EMC explicitly changes the role model.
- Review submitted orders daily and move statuses only through the choices offered by the app.
- Use the server-side Orders filters and pagination instead of exporting or loading the complete order history.
- Use Reports for periods up to 366 days; narrow by package when reviewing package performance.
- Write at least one clear English or Myanmar note for every change; both are preferred.
- Keep package prices fixed for existing orders. Editing a package affects future orders only.
- Hide obsolete packages instead of altering old order records.
- Reset the admin password from the server CLI if access is lost.

## Monitoring and incidents

Monitor HTTPS expiry, `/api/health`, HTTP 5xx rates, free disk space, MySQL availability, backup completion, and the writable photo directory. Never log request bodies, passwords, cookies, CSRF tokens, customer addresses, or photo contents.

For suspected account or server compromise: take the site offline, preserve logs, rotate the app key and database/admin credentials, clear the Laravel `sessions` table, inspect database/photo changes, restore only from a known-good backup, and notify affected customers according to local requirements.
