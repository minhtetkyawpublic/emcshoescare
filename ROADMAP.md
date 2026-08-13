# EMC Shoes Care Myanmar — Web App Roadmap

## Product decisions

- Stack: React frontend, plain PHP REST API, and MySQL. No Laravel or other PHP framework.
- Delivery: mobile-first Progressive Web App (PWA), installable from supported Android and iOS browsers.
- Brand: **EMC Shoes Care Myanmar**; short/PWA name: **EMC**; app icon: `emcicon.jpg`.
- Languages: English and Myanmar. All interface translations live in one editable frontend file.
- Currency: Myanmar Kyat (`Ks`).
- Customer access: phone number and password registration/login, with a persistent remembered session. No OTP.
- Orders: packages have fixed prices; completion duration is not fixed and payment is outside the app.
- Fulfilment: customers may bring/collect shoes or request optional pickup/return service.
- Images: up to 10 shoe photos per order, compressed in the browser before upload.
- Order communication: customers see status changes and an admin note attached to each update.
- Administration: one administrator role only.

## Phase 1 — Upfront-payment demo

Goal: deliver a polished customer-facing sales experience that demonstrates the product before backend development.

- Responsive bilingual landing page
- EMC branding, app icon, service highlights, packages, process, and trust information
- Order form with customer details, package selection, fulfilment choice, and up to 10 photo previews
- Client-side image compression demonstration
- Form validation and a clearly labelled demo submission state (no data is sent yet)
- PWA manifest, install prompt support, and basic offline shell caching
- Keyboard, touch, responsive, and accessibility review
- Production build verification
- Git milestone commit for customer approval/upfront payment

Acceptance: the page looks and works well on phone, tablet, and desktop; switches fully between English and Myanmar; the order form can be completed; image count/size rules are enforced; and the production build passes.

## Phase 2 — Database, PHP API, and customer accounts

Status: **Implemented locally — pending customer UI review**

- Define the MySQL schema and versioned SQL migrations
- Create a small structured PHP API with configuration, routing, validation, and JSON responses
- Secure phone-number/password registration and login (`password_hash` / `password_verify`)
- Remembered login using secure, rotating server-side session tokens
- CSRF protection, rate limiting, input validation, and consistent API errors
- Customer profile and saved address
- Connect the React app to the PHP API
- Add environment/configuration documentation for XAMPP and production hosting

Acceptance: a customer can register, remain signed in, log out, and log back in securely on supported devices.

## Phase 3 — Admin packages and order intake

Status: **Implemented locally — pending customer/admin UI review**

- Single-admin secure login
- Admin dashboard and package CRUD (name, bilingual description, fixed price, active/inactive state)
- Persist customer orders in MySQL
- Store compressed photos safely with generated filenames and private metadata
- Enforce a maximum of 10 images and server-side file/MIME/size checks
- Pickup/return option and fee recorded separately from package price
- Customer order history and order detail screen

Acceptance: packages displayed to customers come from the admin panel, and submitted orders/photos appear correctly for both customer and admin.

## Phase 4 — Status tracking and notes

Status: **Implemented locally — pending customer/admin UI review**

- Status workflow: Submitted → Confirmed → Pickup scheduled → Rider on the way → Shoes received → Repairing → Ready for collection/delivery → Done
- Cancelled status and safe transition rules
- Admin note with every status update
- Customer-facing visual timeline with date/time and note history
- In-app unread update indicator

Acceptance: an admin can update an order, and the customer sees the correct status, history, and note inside the app.

## Phase 5 — PWA completion and resilience

Status: **Implemented; awaiting UI/UX acceptance.**

- Final app icons/splash presentation using `emcicon.jpg`
- Offline fallback and careful caching so account/order data never becomes stale or exposed
- Install guidance for Android and iOS
- Upload retry and recovery for weak mobile connections
- Performance, accessibility, and security hardening

Acceptance: the production site meets installability requirements and remains clear and usable on slow or interrupted networks.

## Phase 6 — Release preparation

Status: **In progress; automated release checks and operations tooling are being completed. Real shop content, device approval, hosting, and backup scheduling remain launch gates.**

- Full bilingual copy review
- End-to-end testing of registration, order submission, photos, admin actions, and status history
- Responsive QA on representative phone, tablet, laptop, and desktop sizes
- Production MySQL setup, HTTPS, backups, retention policy, and upload-directory protection
- Deployment guide and admin/user handover documentation
- Final tagged release

Acceptance: the app is deployment-ready, backed up, documented, and approved through customer and admin acceptance testing.

## Delivery discipline for every phase

Each phase follows the same loop: agree on its screen/workflow, implement only that scope, test behavior and responsive UI/UX, record any decisions, and create a separate Git commit. Later phases must not be mixed into the Phase 1 approval milestone.

## Information to replace before production

- Real package names, bilingual descriptions, and fixed prices
- Shop phone number, address, opening hours, and service area
- Optional pickup/return fee rules
- Final privacy/terms wording and photo retention period
- Production hosting/database credentials (kept outside Git)
