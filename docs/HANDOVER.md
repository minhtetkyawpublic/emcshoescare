# EMC staff and customer handover

## Customer flow

1. Choose English or Myanmar.
2. Create an account using name, Myanmar phone number, and password. No OTP is used.
3. Leave **Remember me** selected on a private device to stay signed in for up to 30 days.
4. Choose a fixed-price package and either shop drop-off or optional pickup.
5. Add one to ten clear shoe photos. The browser compresses them before upload.
6. Submit the order. No payment is collected in the app.
7. Open **My account** to read the current status, timeline, and EMC notes.
8. Install EMC from Android Chrome or, on iPhone/iPad, Safari **Share → Add to Home Screen**.

If an upload stops, reconnect and retry. The unfinished request and compressed photos remain only in that signed-in customer's browser until submission succeeds or the draft is discarded.

## Administrator flow

The app has one administrator role. Open `/admin`, sign in, and use:

- **Orders** to review contact details and private photos, then move the order through the offered next statuses.
- **Packages** to add bilingual fixed-price packages, edit future-facing details, or hide a package.
- **Settings** to set the optional pickup fee in Ks; use `0` for free pickup.

Pickup orders follow Submitted → Confirmed → Pickup scheduled → Rider on the way → Shoes received → Repairing → Ready → Done. Customer drop-off orders skip the pickup/rider states. Cancelled and Done orders are locked. Every status change requires an English or Myanmar note.

## Before staff acceptance

EMC must supply and approve the real package names/prices, address, phone, opening hours, pickup area/rules, privacy wording, and photo-retention period. A Myanmar-fluent reviewer should approve every customer-facing translation. Staff should complete one pickup order and one drop-off order on staging before launch.
