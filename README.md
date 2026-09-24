# Fundraiser Laravel API

Laravel replacement for `../fundraiser-api`, retaining MongoDB and the `/api/v1` API used by the separate React frontend. The Node project remains unchanged.

**Status:** local implementation and verification complete. Production cutover remains pending the environment and integration checks in the [conversion checklist](CONVERSION_CHECKLIST.md).

Implemented organization/manager authentication, profiles, projects, activities, donations, wallets, manual withdrawals, reporting, uploads, provider clients, and durable email delivery. Existing MongoDB collection names, ObjectIds, timestamps, bcrypt hashes, and compatible JWTs are preserved. Authorization and financial integrity corrections are documented in [API.md](docs/API.md).

## Run locally

Requires PHP 8.4+, Composer 2, MongoDB extension 2.4+, BCMath, and a MongoDB replica set. Follow [setup and operations](docs/OPERATIONS.md) for a fresh installation or Docker setup.

This workspace already has dependencies and a local-only `.env`. Its isolated MongoDB is configured on port 27028. From this folder:

```sh
./scripts/php artisan fundraiser:preflight
./scripts/php artisan serve
```

Run `./scripts/php artisan schedule:work` in another terminal for email delivery. Set the React origin in `CORS_ALLOWED_ORIGINS`, its URL in `FRONTEND_URL`, and its API base URL to `http://localhost:8000/api/v1`. Configure provider and SMTP credentials before using external integrations.

The `scripts/php` wrapper loads this workspace's ignored local MongoDB extension when necessary; that compiled binary is machine-specific. Install the extension normally on other machines or use Docker.

## Verification

34 tests / 320 assertions passed against isolated real MongoDB, including concurrent financial writes, Node bcrypt/JWT compatibility, 13 original Node response fixtures, BSON serialization, and native multipart PUT requests. All 72 original route registrations resolve to the corresponding Laravel routes; the duplicate donation registration is consolidated.

Formatting, Composer validation/platform requirements, route caching, and dependency audit passed. A synthetic backup restored eight collections/four documents with byte-for-byte BSON equality and passing reconciliation. Docker configuration was validated; Docker builds and hosted CI have not been executed.

See [route coverage](ROUTE_INVENTORY.md), [React API integration](docs/API.md), and [deployment/backup/rollback procedures](docs/OPERATIONS.md). Real provider sandbox tests, React acceptance, production data rehearsal, and deployment remain open.
