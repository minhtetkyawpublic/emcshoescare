# EMC release checklist

This is the authoritative Phase 6 approval record. Do not create the final release tag until every launch gate is checked.

Record the human/device evidence in `docs/ACCEPTANCE_TEST.md`; use `docs/CONTENT_WORKSHEET.md` for the approved shop copy and package data.

## Implemented and automatically verified

- [x] React customer/admin interfaces with Laravel 12 and MySQL
- [x] Phone/password registration and remembered login without OTP
- [x] English/Myanmar copy stored together in `src/i18n/translations.js` with matching keys
- [x] Fixed-price packages in Ks managed by the single administrator role
- [x] Customer drop-off and optional-fee pickup paths
- [x] One to ten compressed photos stored privately
- [x] No in-app payment workflow
- [x] Guarded status transitions, bilingual notes, timeline, and unread state
- [x] Browser-installable EMC PWA with final regular, maskable, and Apple icons
- [x] Safe offline shell; API, account, admin, order, and private-photo responses are never cached
- [x] Recoverable, customer-bound interrupted submissions with duplicate protection
- [x] Apache security rules and production configuration guards
- [x] Tested database/photo backup command with checksum
- [x] Dry-run-first terminal-order photo-retention command
- [x] Deployment, operations, and staff/customer handover guides
- [x] SSH-ready `dist` package deployable at a domain root or arbitrary nested folder
- [x] Laravel Artisan migrations and seed data verified on fresh MySQL and repeated runs
- [x] Private Laravel runtime, environment, migrations, Composer packages, and photos remain outside `public_html`

## Required business inputs

- [ ] Real bilingual package names, descriptions, and fixed prices approved
- [ ] Shop phone, address, opening hours, service area, and pickup rules supplied
- [ ] Privacy/terms wording and photo-retention period approved
- [ ] Myanmar copy approved by a fluent human reviewer

## Hosting and recovery gates

- [ ] Production domain and HTTPS certificate configured
- [ ] Dedicated non-root MySQL runtime account and production app key configured
- [ ] CLI migration status shows all migrations applied and single administrator created securely
- [ ] Private photo directory permissions and direct-access denial verified
- [ ] Daily off-site encrypted backup schedule enabled and monitored
- [x] A local backup restored successfully into an isolated scratch database and matched expected tables/migrations/data
- [ ] Retention cleanup schedule matches the published privacy policy

## Device and workflow acceptance

- [ ] Android Chrome: register, remember login, submit ten photos, track order, install app
- [ ] iPhone Safari: register, remember login, submit photos, track order, Add to Home Screen
- [ ] Tablet portrait/landscape: customer and administrator workflows approved
- [ ] Laptop/desktop: customer and administrator workflows approved
- [ ] Slow/interrupted network: saved draft, retry, offline notice, and no duplicate order approved
- [ ] Staff complete one pickup order and one drop-off order through Done on staging
- [ ] Cross-account order and photo access rejected on staging

## Release approval

- [ ] EMC owner accepts the UI/UX and real content
- [ ] Deployment owner signs off the backup/restore evidence
- [ ] Version is changed from the release candidate to `1.0.0`
- [ ] Phase 6 commit is tagged `v1.0.0`
