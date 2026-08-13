# EMC launch acceptance record

Use this document on the staging site after real shop content is loaded. Record actual observations; do not check an item from source review or automation alone. Attach screenshots or short screen recordings to the release record where useful.

## Test release

- Staging URL:
- Git commit (`git rev-parse HEAD`):
- Test date/time and Myanmar timezone:
- EMC owner:
- Staff tester:
- Myanmar-language reviewer:
- Deployment owner:

## Content approval

Complete `docs/CONTENT_WORKSHEET.md`, enter the approved packages and pickup fee in `/admin`, then verify:

- [ ] Shop name is **EMC Shoes Care Myanmar** and installed short name is **EMC**.
- [ ] Phone, address, opening hours, service area, and pickup rules match the approved worksheet.
- [ ] Every active package has the approved English/Myanmar name, description, fixed price, and display order.
- [ ] Prices display in `Ks`; no duration promise or in-app payment control appears.
- [ ] English and Myanmar screens convey the same meaning and the Myanmar reviewer approves the copy.
- [ ] Privacy, cancellation, and photo-retention wording match the approved policy.

Content result: PASS / FAIL

Notes or evidence links:

## Device matrix

For each row, record the real device/browser version, test both English and Myanmar, and check only after the full scenario passes.

| Device | Browser/version | View and navigation | Forms/photos | Account/status | Install | Tester/date |
| --- | --- | --- | --- | --- | --- | --- |
| Android phone | Chrome: | [ ] | [ ] | [ ] | [ ] | |
| iPhone | Safari: | [ ] | [ ] | [ ] | [ ] | |
| Tablet portrait | Browser: | [ ] | [ ] | [ ] | [ ] | |
| Tablet landscape | Browser: | [ ] | [ ] | [ ] | [ ] | |
| Laptop/desktop | Browser: | [ ] | [ ] | [ ] | N/A | |

View and navigation means there is no clipped text, horizontal overflow, hidden control, unreadable Myanmar text, or unusably small touch target. Keyboard focus must remain visible on laptop/desktop.

Install means Android opens EMC in standalone mode from its install action, while iPhone uses Safari **Share → Add to Home Screen**. The home-screen icon must use the supplied EMC artwork and display the name **EMC**.

## Customer and administrator scenario

Use a new test phone number and real sample shoe photos. Do not reuse a customer or order from an earlier acceptance run.

- [ ] Register with phone number and password without OTP.
- [ ] Leave **Remember me** enabled, close all browser windows, reopen the staging URL, and confirm the same account remains signed in.
- [ ] Choose an active fixed-price package and customer drop-off; confirm pickup fee is `0` for this order.
- [ ] Upload exactly ten different photos, confirm usable previews, and submit once.
- [ ] Confirm only one order exists after refreshing/retrying the result page.
- [ ] Staff can open the order and all ten photos in `/admin`.
- [ ] Staff advances the drop-off order through Confirmed → Shoes received → Repairing → Ready → Done, adding a meaningful customer note each time.
- [ ] Customer sees every status, timestamp, note, and unread indicator, then opening the order clears the unread indicator.
- [ ] Create a second order with pickup and confirm the configured pickup fee is added once to the fixed package price.
- [ ] Staff advances it through Confirmed → Pickup scheduled → Rider on the way → Shoes received → Repairing → Ready → Done.
- [ ] No payment request, payment button, or payment status appears anywhere.

Workflow result: PASS / FAIL

Test customer phone and order numbers:

Notes or evidence links:

## Resilience and privacy scenario

- [ ] Begin an order with photos, interrupt connectivity before submission, and confirm the offline/draft message is understandable.
- [ ] Reconnect and retry; confirm exactly one order is created and the saved draft disappears after success.
- [ ] While offline, account/order/admin pages never show another person’s previously cached private data.
- [ ] Sign in as a second customer and request the first customer’s order and photo URLs; both return Not Found.
- [ ] Sign out and confirm authenticated order/photo URLs no longer open.
- [ ] Direct browsing to `storage/order-photos` is denied.

Resilience/privacy result: PASS / FAIL

Notes or evidence links:

## Production and recovery evidence

- [ ] Production uses HTTPS with the final domain and no browser certificate warning.
- [ ] `EMC_APP_ENV=production`, a unique 32+ character `EMC_APP_KEY`, the exact HTTPS origin, and a dedicated password-protected non-root MySQL account are configured outside Git.
- [ ] All four migrations are recorded and the single administrator can sign in.
- [ ] Upload permissions work, while direct photo-directory access is denied.
- [ ] An encrypted off-site backup schedule is enabled and its latest run/checksum is recorded.
- [ ] A backup is restored into an isolated database and the expected tables, migrations, packages, and a private photo are verified.
- [ ] Photo-retention cleanup is scheduled with the approved number of days and is first observed in dry-run mode.

Production/recovery result: PASS / FAIL

Backup job/run and restore evidence:

## Final sign-off

Every result above must be PASS and every checkbox complete before `v1.0.0`.

- EMC owner — name/signature/date:
- Staff workflow tester — name/signature/date:
- Myanmar reviewer — name/signature/date:
- Deployment owner — name/signature/date:
- Approved commit SHA:
- Release decision: APPROVED / NOT APPROVED

