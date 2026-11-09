# Spookremmen Meldpunt: phantom braking reporting point

A central place to report **phantom braking**, where a car's automatic emergency brake (AEB) or adaptive cruise
control brakes hard for no reason. It starts with the Netherlands. The vehicle is identified via the
[RDW open data](https://opendata.rdw.nl) register, and the domain is ready for other EU registries.

Context: since 7 July 2024 AEB is mandatory in every newly registered car in the EU (GSR2, Regulation 2019/2144).
Kassa (BNNVARA, Nov 2026) estimates *at least hundreds* of incidents a year in NL. The official ILT reporting point
received only ~20 reports in four years, and manufacturers aren't required to register false interventions.

## Stack

Symfony 8.1 · Doctrine ORM · Messenger (command + event bus) · Twig · **AssetMapper** · **Stimulus** ·
**Tailwind v4** (symfonycasts/tailwind-bundle) · Chart.js (symfony/ux-chartjs) · Leaflet + OpenStreetMap.
Dev runs in Docker (PHP 8.5-FPM, nginx, Postgres 17, a Tailwind watcher) behind the shared Traefik proxy.
The Docker and Make setup is borrowed from `ehbo-systeem-2.0`.

## Getting started

```bash
make install     # .env.local secrets, Traefik cert, build, up, /etc/hosts (sudo), composer, db, assets
```

Then open https://phantom-braking.dev. The first time, trust the shared root CA if you haven't already
(`make traefik-setup` prints the command). `make help` lists every target. The ones you'll use most:

| Target | What it does |
|---|---|
| `make up` / `make down` | Start or stop the stack (Traefik is started if needed) |
| `make console cmd="…"` | Run `bin/console` in the php container |
| `make migration` / `make migrate` / `make db-setup` | Doctrine migrations / reset the dev database |
| `make seed count=300 country=DE with_media=20` | **Dev only:** insert random `[DEMO]` reports; `with_media` gives that many of them a demo video (`.dev/demo-media/`) and/or generated photos, in a mix of moderation states |
| `make media` / `make media action=approve type=photo id=<uuid>` | Moderate dashcam footage and photos |
| `make test` | PHPStan (level 8, no baseline) + Unit + Functional + E2E |
| `make cs-fix` / `make pre-commit` | PHP-CS-Fixer + phpcbf / PHPStan + style |

Tests: `tests/Unit` (pure domain), `tests/Functional` (command handlers against in-memory doubles in
`tests/Double`), `tests/E2E` (real kernel and HTTP client on a throwaway SQLite database; the RDW is replaced by
`InMemoryVehicleRegistry`).

Configuration: `.docker/.env` (project name, URL, database credentials), `.env.dev` (Postgres URL, trusted
proxies), and `.env.local` (`APP_SECRET` and `PLATE_FINGERPRINT_KEY`; **keep the fingerprint key stable**).
Optional: set `RDW_APP_TOKEN` (Socrata app token) for higher RDW rate limits. Uploads (a video up to 300 MB plus up to 6 photos of 15 MB) are allowed by
`.docker/php/php-ini-overrides.ini` and `.docker/nginx/nginx.conf`; keep both in sync with
`ReportIncidentData::MAX_FOOTAGE_SIZE`.

## Architecture (DDD, see `src/`)

| Module | Responsibility |
|---|---|
| `SharedKernel` | Bus/handler contracts, UUID `AbstractId`, base repository, Doctrine ID type, ID value resolver |
| `Vehicle` | `LicensePlate`, `VehicleSnapshot` (embeddable), `VehicleRegistryInterface` → `CountryRoutingVehicleRegistry` → one `CountryVehicleRegistryInterface` per country (NL: `RdwVehicleRegistry`, datasets `m9d7-ebf2` + `8ys7-d773`, cached 24h), keyed plate fingerprint, live lookup API |
| `Incident` | `Incident` aggregate (+ `Location`, `DashcamFootage`, `Photo`), `ReportIncidentCommand`, media moderation, image sanitising, report form, list/detail, media streaming, `IncidentFilter` + the shared filter bar |
| `Statistics` | Read model (`StatisticsOverview`) via DQL aggregates, home dashboard, map-points API |

### Countries

A report has **two** countries:

- **Registration country** (`Incident::$country`, enum `Country`): which national register the plate was looked up in.
  Only countries with a registry integration (see below).
- **Incident country** (`Location::$country`, `IncidentCountry`): where it happened. This can be any European country, since a
  Dutch car can phantom-brake on a German Autobahn. Filters, road badges, "most reported roads" and the map
  use this one.

We only accept reports from countries whose national registry we can query, so make, model and year always come
from an authoritative source. Today that's the Netherlands. Germany and France are *known* countries (their data can
be filtered) but not reportable: neither has an open plate register. Research on DE, BE and FR, including commercial
options and the EU registration-certificate alternative, is in [docs/country-registries.md](docs/country-registries.md).

To add a country:

1. add a case to `Vehicle\Domain\ValueObject\Country` (with `centre()` / `mapZoom()` for the map) and a
   `country.xx` translation;
2. implement `CountryVehicleRegistryInterface` in `Vehicle/Infrastructure/Service`. It's picked up automatically
   (tag `app.country_vehicle_registry`).

The country then shows up in the report form, and the filter bar gets a country selector once there are two or more.
Remove the `ignoreErrors` entry in `phpstan.dist.neon` at that point (it exists only because `Country` has a single case).

### Filters

The dashboard, the reports list and `/api/incidents/map` share one set of query parameters:
`country` (the **incident** country, where it happened, not where the vehicle is registered), `make`, `model` (only with `make`), `road_type`, `period` (`30d`, `90d`, `12m`, `ytd`, or `custom` with
`from`/`to` as `YYYY-MM-DD`). Unknown values are ignored. Filtered views can be bookmarked, and the filter bar
carries the filter between the statistics and the list.

## What we measure and why

Every metric targets a question investigators, the RDW/ILT or manufacturers currently can't answer:

- **Vehicle (from the RDW, not typed by the user):** make, model, first registration date (pre/post GSR2),
  fuel/hybrid class, **type-approval number and variant**. These identify the shared technical platform (and ADAS
  hardware) across brand names. Also shows whether a recall is open.
- **Location:** road type (motorway, provincial road, urban, …), **road situation at the exact spot** (under a viaduct,
  tunnel entrance/inside/exit, bridge, sign gantry, on/off-ramp, merge, roadworks, …), road number, direction,
  speed limit, and GPS point → hotspot map and "most reported roads".
- **Event:** severity (warning only → full emergency stop), speed before, lowest speed during (→ speed lost),
  suspected trigger (shadow, viaduct/gantry, truck in the next lane, oncoming traffic, low sun, …), active system
  (none = always-on AEB vs ACC vs ACC + lane centring), whether a dashboard warning appeared.
- **Conditions:** weather, light (low sun is a known camera issue), time of day.
- **Impact:** consequence, especially whether the **car behind was endangered** (rear-end risk is the real danger).
- **Aftermath:** how often this car does it, the dealer's response (share *dismissed* = no fault found / blamed
  driver / refused), whether it was also reported to the ILT (shows under-reporting), recent windscreen replacement
  (camera recalibration), odometer and software version.

## Road lookup from the map

Clicking the map in the report form calls `POST /api/road-lookup`, which reverse-geocodes the point with
OpenStreetMap **Nominatim** (`NominatimRoadLocator`, zoom 16 = "major streets") and suggests country, road number
(national `ref`, not the E-route), road type, tunnel/bridge and speed limit. Suggestions only fill fields that are empty
or were filled by an earlier suggestion. Following the [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/):
requests go through our server (no visitor IPs reach OSM) with an identifying User-Agent (set `DEFAULT_URI`), a
site-wide limit of 1 request per second (`nominatim` rate limiter) and a 30-day cache per ~11 m cell. At real traffic
volumes, switch to a self-hosted or commercial Nominatim by changing the `nominatim.client` base URI.

## Privacy and moderation

- The **license plate is never stored**. We keep an HMAC-SHA256 fingerprint (`PLATE_FINGERPRINT_KEY`) so we can count
  distinct and repeat vehicles. Changing the key breaks those counts for existing data.
- GPS points are rounded to 4 decimals (~11 m).
- Dashcam footage and aftermath photos (max 6, JPEG/PNG/WebP, 15 MB each) are stored outside the web root
  (`FOOTAGE_STORAGE_DIR/YYYY/MM/<incident>/`) and served **only** after a moderator approves them, and only if the
  reporter consented to publication.
- Photos are **re-encoded with GD** (`GdImageSanitizer`): EXIF orientation is applied, then everything else
  (GPS, device, XMP and anything appended to the file) is dropped. They are scaled to at most 2560 px and the
  original upload is deleted. Non-images and decompression bombs (more than 40 MP) are rejected.
  ```bash
  make media                                         # list pending footage and photos
  make media action=approve type=photo id=<photo-id> # or: type=footage id=<incident-id>, action=reject
  ```
- Rate limits: 30 plate lookups/min and 5 reports/hour per IP (`TRUSTED_PROXIES` makes the real client IP visible behind Traefik and nginx).

## Media storage (S3)

Photos and videos live in two **private** S3-compatible buckets (so each can be monitored and managed on its own): [Sliplane Object Storage](https://sliplane.io/pricing#object-storage)
in production (choose the **Germany** region, so media stays in the EU), RustFS in dev (`.dev/docker-compose.yml`), local
disk in the test suite (`config/services_test.yaml`). Nothing in the bucket is ever public: `/footage/{id}` and
`/photos/{id}` stream objects through the app (`MediaResponder`, with byte ranges so videos can be seeked) after the
moderation checks, and `/admin/media/…` does the same for moderators. Egress is free at Sliplane, so streaming costs nothing extra.

| Variable | |
|---|---|
| `MEDIA_S3_ENDPOINT` | e.g. the endpoint shown in the Sliplane dashboard |
| `MEDIA_S3_REGION` | as given by the provider (most S3-compatible services accept any value) |
| `MEDIA_S3_PHOTO_BUCKET` / `MEDIA_S3_VIDEO_BUCKET` | `phantom-braking-photos` / `phantom-braking-videos` by default |
| `MEDIA_S3_PHOTO_ACCESS_KEY` / `MEDIA_S3_PHOTO_SECRET_KEY` | credentials of the photo bucket; **server env / `.env.local` only** |
| `MEDIA_S3_VIDEO_ACCESS_KEY` / `MEDIA_S3_VIDEO_SECRET_KEY` | credentials of the video bucket (Sliplane issues keys per bucket; with one key for both, set it twice) |
| `MEDIA_S3_PATH_STYLE` | `true` for most S3-compatible services |

`make s3-bucket` creates both buckets (idempotent, also part of `make install`). The admin dashboard shows their
size (listed via S3, cached 15 minutes) with an indicative Sliplane cost. `make media-import` copies media that
was stored on local disk before (`var/storage/footage`) into the bucket under the same keys.

## Error monitoring (Sentry)

Backend errors go to Sentry when `SENTRY_DSN` is set (empty = off; tests never send). Use an **EU-hosted** Sentry
organisation. Because this is a reporting point for vehicles, `config/packages/sentry.yaml` is strict on purpose:

- `send_default_pii: false`: no IP addresses, cookies or user data;
- `max_request_body_size: never`: request bodies contain license plates (the SDK's default would send them);
- `before_send` / `before_breadcrumb` (`SensitiveDataScrubber`): scrub plates from messages, exceptions, URLs
  (`?kenteken=…`), request data and breadcrumbs, as a safety net;
- performance tracing is off: it would record outgoing request URLs, and the RDW one contains the plate.

`SentryScrubbingTest` and `LegalPagesTest::testErrorMonitoringIsConfiguredNotToSendPersonalData` guard these settings.
Errors are grouped per release (`phantom-braking@<version>+<build>`): a deploy writes `version.json` (git tag and commit), which `VersionEnvVarLoader` exposes as `APP_VERSION`/`APP_BUILD`; in dev they come from git. The footer shows the version, linked to the commit.

## Admin

`/admin` is the moderation area. Accounts live in the `admin_user` table with only a **name**: no e-mail address and
**no password**. Admins log in with a **passkey** (WebAuthn, via `web-auth/webauthn-symfony-bundle`; user
verification required, discoverable, so nothing is typed). Passkeys are stored in `admin_passkey` (public key and signature counter only).

- `make admin name="Your Name"` creates an admin and prints a **one-time login link** (15 minutes, single use,
  only redeemed by a POST so link previews can't use it up). It lands on `/admin/passkeys`, where you add a passkey.
- `make admin-link name="Your Name"` prints a new link: for a new device, or to recover after losing every passkey.
  Whoever can run it can log in as that admin, which is why the link is only printed on the server, never e-mailed.
- `/admin/passkeys` lists, adds and removes passkeys; the last one can't be removed. Add at least two (e.g. phone and laptop).
- The relying-party id and allowed origin come from `DEFAULT_URI`, so set it to the public URL in production.
  Passkeys are bound to that domain: changing the domain means every admin adds a new passkey via a login link.
- Login attempts are throttled (5 per 15 minutes).

- **To review**: reports with footage or photos awaiting moderation. Per photo the moderator sees the private original
  with the plate masks the reporter drew, drags extra boxes over any readable plate and saves: the public copy is
  regenerated from the original with solid black boxes (`GdImageRedactor`). Only that copy is ever served at `/photos/{id}`.
  Footage can't be masked, so it's only approved when no plate is readable.
- **Deletion requests**: a reporter requests deletion on the report page by entering the plate (it must match the
  stored fingerprint, otherwise no request is made). Approving deletes all footage and photo files of the report from storage;
  denying clears the request. The report itself stays.

`make media` (CLI moderation) still works alongside it.

## Legal pages

`/{nl,en,de,fr}/privacy`, `/cookies` and `/terms` are hand-written templates in `templates/legal/` (one per page and
language). They describe what the code actually does, so **update them when data handling changes**, and bump
`ShowLegalPageController::LAST_UPDATED`. Set `OPERATOR_NAME`, `OPERATOR_EMAIL` and optionally `OPERATOR_ADDRESS` (the
data controller) before going live; until then the pages show a visible placeholder.

Before going live, also make sure these commitments in the privacy statement hold in production: web-server logs
are kept for at most **14 days**, and the hosting provider has a data processing agreement.

## Languages, About and FAQ

The site is available in Dutch, English, German and French (`/{nl,en,de,fr}/…`). Interface strings live in
`translations/*+intl-icu.{locale}.yaml`; the legal pages are one template per language. To add a language: add it
to `framework.enabled_locales` (`config/packages/translation.yaml`) **and** `app.locales` (`config/services.yaml`,
the route requirement), translate both YAML files and the three legal templates, and check
`bin/console debug:translation <locale> --only-missing`.

`/{locale}/about` and `/{locale}/faq` (module `src/Info/`) are rendered from translations. FAQ questions are listed in
`ShowFaqController::QUESTIONS`; each id needs `faq.<id>.q` and `faq.<id>.a` in every language. The FAQ also emits
`FAQPage` structured data for search engines.

## Deployment

Production runs on Proxmox in its own Debian LXC (nginx, PHP 8.5-FPM, PostgreSQL, supervisor), behind the existing
reverse proxy, at **phantombraking.eu**; `spook-remmen.nl` redirects to `/nl`. Deploy with `make deploy` (Deployer,
`deploy.php`); roll back with `make deploy-rollback`. Server configuration lives in `.deploy/` (run
`.deploy/provision.sh` once in a fresh container). Full walkthrough: [docs/deployment.md](docs/deployment.md).

## Planned: research MCP server

A paid MCP server for researchers is designed but not built: read-only, only the already-public, plate-less data,
with re-identification safeguards, API keys per organisation and Mollie billing. Design, data rules and what's worth
doing now are in [docs/research-mcp-server.md](docs/research-mcp-server.md).

## Not built yet / next steps

- Normalise reports per make/model against **fleet size** (RDW count per `merk`/`handelsbenaming`), so popular
  brands don't look worse just by volume.
- Spam protection beyond rate limiting (e.g. a honeypot or a privacy-friendly captcha), and an optional e-mail for follow-up.
- Open-data export (CSV/JSON) of the anonymised reports for researchers, journalists and the ILT.
- Automatic blurring of faces and plates in footage and photos (now masked by hand by the reporter and a moderator).
- An async transcode step for footage (videos are stored as uploaded).

## License

[MIT](LICENSE) © LauLaman Apps. Source: <https://github.com/LauLamanApps/phantom-braking>.
