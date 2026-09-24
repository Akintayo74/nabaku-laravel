# Node.js → Laravel conversion checklist

Updated 2026-09-23. Local implementation is complete; unchecked release gates require external environments. MongoDB remains the database. The separate React project connects through `/api/v1`.

## 1. Foundation and baseline

- [x] Create sibling `fundraiser-laravel` project without modifying the Node application.
- [x] Inspect all mounted routes, 11 domain models, providers, authentication, and financial workflows.
- [x] Scaffold Laravel 13; install and lock stable dependencies, including the official MongoDB integration.
- [x] Require PHP 8.4+ and MongoDB extension 2.4+; verify local platform requirements and database connectivity.
- [x] Preserve `/api/v1`, root welcome response, and withdrawal aliases; consolidate duplicate donation registration.
- [x] Configure environment-based CORS for the separate React client, JWT, JSON errors, throttling, request IDs, and health checks.
- [x] Remove SQL migrations/bootstrap and frontend build dependencies. Use MongoDB models, file sessions, supported MongoDB cache/locks, and a durable MongoDB email outbox.
- [x] Provide placeholder-only `.env.example` and separate API `APP_URL` from `FRONTEND_URL`.
- [x] Capture 13 original Node responses on synthetic fixtures with external effects disabled; preserve a reproducible capture script.
- [x] Inventory all 72 source route registrations and test their resolution. Document intentional API corrections.
- [ ] Expand exact original-runtime response comparisons beyond the 13 captured fixtures if staging identifies additional client dependencies.

## 2. MongoDB compatibility

- [x] Implement all 11 original domain collections; donations remain in `transactions`.
- [x] Preserve native ObjectIds, reference types, camelCase timestamps, embedded BSON arrays, and nested media IDs.
- [x] Preserve compatible `_id`/`id`, millisecond date serialization, selected `__v` behavior, and populated responses; redact private fields.
- [x] Implement scoped filtering, pagination, ordering, relationship loading, and project `is_deleted` semantics.
- [x] Use exact financial arithmetic before writing compatible major-unit numbers; preserve existing ledger meaning.
- [x] Provide read-only topology/index checks, BSON/orphan/money audits, and financial reconciliation commands.
- [x] Provide rerunnable additive indexes with duplicate checks; do not drop existing indexes or reset data.
- [x] Provide a protected backup/restore script and rehearse it on synthetic data: eight collections, four documents, zero failures, identical BSON, clean audits/reconciliation.
- [ ] Inspect the actual production MongoDB version/topology, collection names, indexes, schema drift, duplicates, and orphan references.
- [ ] Restore a production snapshot in secured staging and verify counts, balances, IDs, and all required index changes.
- [ ] Resolve historical balance discrepancies and legacy withdrawals without reservations; rehearse any required data-specific backfill.

## 3. Authentication and access

- [x] Port organization and manager registration/login/activation, password reset, profile setup, states, and admin activation.
- [x] Verify Node bcrypt hashes and HS256 JWT fixtures; preserve stateless multi-device tokens and the existing token lifetime.
- [x] Resolve manager access through the parent organization; enforce owner/admin boundaries and inactive/blocked restrictions.
- [x] Hash new reset/activation tokens at rest, expire them, and consume them atomically once.
- [x] Provide explicit legacy activation grace-period configuration and a safe admin creation command without default credentials or account overwrites.
- [ ] Verify production JWT secret suitability and outstanding activation/reset links against actual React routes before cutover.

## 4. Business modules

- [x] Organizations: public/private profiles, related documents, dashboard, overview, search, avatar/password updates, blocking, and archival.
- [x] Managers: invitations, activation, membership limits, scoped deletion, and invitation mail. Retain legacy GET deletion and add DELETE for client migration.
- [x] Banks: listing, provider account verification, ownership, create/update/get, account formatting, and verification invalidation after changes.
- [x] Projects: CRUD, organization/admin/public feeds, random projects, multipart cover/media uploads, totals, and visibility flags.
- [x] Activities: CRUD and public feeds; validate dates including partial updates.
- [x] Donations: public checkout, references, verification/webhook settlement, detail/lists, and yearly graphs.
- [x] Wallets and withdrawals: balances, reservations, aliases, admin transitions, manual completion, and reconciliation.
- [x] Global reporting: scoped aggregates, admin dashboard, and organization/project search.
- [x] Files and mail: Cloudinary uploads with validation; Blade notification templates and encrypted durable email outbox.
- [x] Audit unused spreadsheet/image/scheduler dependencies and disabled routes; avoid introducing unreachable features.

## 5. Payment integrity

- [x] Implement Flutterwave, Monnify balance, and Cloudinary clients with timeouts and mocked contract tests.
- [x] Reject invalid webhook signatures before mutation; verify reference, provider status, amount, and currency before credit.
- [x] Save checkout references before calling the provider; retain ambiguous timeout state for reconciliation without blindly retrying creation.
- [x] Use unique payment keys, guarded transitions, MongoDB transactions, and bounded transient transaction retries.
- [x] Test duplicate/concurrent delivery, rollback, out-of-order events, and exactly-once credit.
- [x] Reserve withdrawal funds atomically, enforce ownership/sufficient funds, and release/debit once on terminal transitions.
- [x] Preserve the source's manual withdrawal completion workflow; no automatic Monnify disbursement.
- [x] Keep external calls outside database transaction retries; implement leased outbox delivery, failure recovery, and payment verification/reconciliation commands.
- [ ] Confirm the configured webhook signature contract and complete real provider sandbox checkout, bank verification, balance, upload, and SMTP flows.

## 6. Local verification

- [x] Pass 34 tests / 320 assertions on a real isolated MongoDB replica set, with mocked external effects.
- [x] Cover mounted route handlers and route aliases; compare 13 original Node responses with explicit intentional changes.
- [x] Test anonymous/organization/manager/admin/inactive/blocked and cross-organization access.
- [x] Test payment mismatch/timeouts, concurrent settlement/withdrawals, insufficient funds, outbox retries, and financial rollback.
- [x] Verify native multipart PUT through an HTTP server, CORS, and request IDs.
- [x] Verify BSON embedded arrays/ObjectIds and Node-generated bcrypt/JWT compatibility.
- [x] Pass formatting, Composer strict validation, platform checks, dependency audit, and route-cache build.
- [x] Add CI and Docker PHP/Nginx/MongoDB/scheduler configuration; validate Compose configuration.
- [ ] Execute the Docker image build and hosted CI pipeline (local Docker daemon was unavailable).
- [ ] Run the separate React project against Laravel in staging through all public, account, project, donation, dashboard, and withdrawal flows.
- [ ] Compare reports against a production snapshot and profile critical queries at realistic scale.

## 7. Deployment and cutover

- [x] Document API contracts, environment mapping, setup, operations, backup, cutover, monitoring, and rollback.
- [ ] Select/configure the deployment host, production secrets, authenticated MongoDB, TLS, scheduler, monitoring, and retention.
- [ ] Rehearse staging deployment, additive changes, and rollback; record outstanding financial states and reconciled totals.
- [ ] Run comparative reads with outbound effects disabled and one authoritative financial writer.
- [ ] Freeze/drain writes, verify a final backup, apply tested changes, reconcile, and switch API/webhook routing.
- [ ] Resume Laravel writes and monitor auth errors, webhook lag, payment states, outbox failures, and balances.
- [ ] Reconcile all post-cutover events before any rollback; never restore an old snapshot over new donations.
- [ ] Complete the agreed observation period and retire the Node deployment.

**Completion boundary:** the application has been implemented and tested locally. Production conversion is complete only after the separate React client and providers operate through Laravel, the production data rehearsal and rollback pass, and Node no longer processes live traffic. No production credentials/data or deployment host were supplied, so those gates remain open.

See [route inventory](ROUTE_INVENTORY.md), [API integration and intentional changes](docs/API.md), and [operations runbook](docs/OPERATIONS.md).
