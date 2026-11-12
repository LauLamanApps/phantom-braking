# Changelog

All notable changes to this reporting point. Versions follow [Semantic Versioning](https://semver.org); the format is
based on [Keep a Changelog](https://keepachangelog.com).

## [Unreleased]

## [1.1.0] - 2026-11-12

### Added

- After reporting a car on which automatic emergency braking is mandatory (first registered on or after 7 July 2024),
  and that you haven't reported to the ILT yet, you're asked to report it to the ILT as well. The page gives a
  ready-made text in Dutch or English to copy, and the steps to the ILT's "Overige onderwerpen" form. Answer "I
  reported it to the ILT" and your report shows it was reported, or choose "Not now". The report records when a
  confirmation came from this page, so we can see whether the prompt works.

### Fixed

- Buttons in forms no longer break the page; the form theme assumed every field has a "required" setting.

## [1.0.3] - 2026-11-11

### Added

- Light and dark mode: choose "System", "Light" or "Dark" at the top of every page. The site follows your device by
  default; a choice of your own is remembered in a cookie (see the cookie notice).
- A glossary in the main menu that explains the terms used on the site, such as AEB, ACC, GSR2, type approval and
  sign gantry.

### Changed

- The statistics map shows up to 20,000 reports (was 2,000) and fills its card next to the charts.

### Fixed

- The statistics map could show fewer reports than the numbers above it for up to 5 minutes after new reports came
  in; it now always matches.

## [1.0.2] - 2026-11-10

### Added

- A fullscreen button on every map.
- Reports close together on the statistics map are grouped into a cluster with their count, so the map stays fast;
  zoom in or click a cluster to see the individual reports.
- Automated checks on every change (tests, static analysis, coding standard and security advisories via GitHub
  Actions) and weekly dependency updates via Dependabot.

### Changed

- New names that put the reporting point first: "Meldpunt spook-remmen" (was "Spookremmen Meldpunt"), "Phantom
  Braking Reporting Point" (was "Phantom Braking Report") and "Meldestelle Phantombremsungen" (was
  "Phantombremsungen-Meldestelle"). The French name already did.
- New headline on the home page and the share images: "Onterecht remmen? Meld het." (English: "Braking for no
  reason? Report it.").
- "Maximumsnelheid" now reads "Toegestane maximumsnelheid" (German: "Zulässige Höchstgeschwindigkeit").
- A report page now opens with the driver's description, then the video, photos and circumstances; the map, vehicle
  and removal request are in the side column.
- The removal request on a report page has a short title ("Verwijderverzoek"); the explanation says who it is for.

### Fixed

- A dashcam video or photo could fail to load when the connection to the storage stalled: such requests are now retried
  after 10 seconds, instead of failing after a minute. If the storage stays unreachable, the page gets a temporary
  error (503) rather than a server error.

## [1.0.1] - 2026-11-10

### Added

- Search engine support: a title and description per page, canonical links, links between the language versions,
  `robots.txt`, a sitemap with every page and report in every language, and structured data for the site.
- Link previews on social media and in chat apps: a share image per language, and on a report page its approved,
  plate-masked photo.
- This changelog, and the version in the footer.
- A menu on phones.

### Changed

- About moved from the footer to the main menu; the footer keeps the changelog, legal pages and source code.
- A dashcam video on a report page only starts loading once the rest of the page has loaded.
- The server runs more PHP workers, so visitors watching a video don't hold up everyone else.

### Fixed

- A report page with a slow video loaded its map only after the video.
- On phones the header took three rows, the language menu opened partly off-screen and the home page scrolled sideways.
- The caret in dropdowns sat against the edge.

## [1.0.0] - 2026-11-09

The first public version.

### Added

- Report a phantom braking incident in about 3 minutes: the licence plate is only used to fetch the make, model and
  year from the RDW (Dutch vehicle registry) and is never stored, only an irreversible fingerprint to count repeat
  reports per vehicle.
- Location on a map, with road number and road type suggested from OpenStreetMap.
- Up to 6 photos and a dashcam video per report. Photos are published only after review, with licence plates masked;
  footage only after review and with the reporter's consent. Reporters can ask for their media to be deleted.
- Statistics: reports per month, make, model, country, road type and situation, speed, time of day and more, with
  filters and a map.
- A public list of all reports, each with its own page.
- Tesla Assisted Driving (TAD, previously FSD) as an assistance system for Teslas.
- Dutch, English, German and French.
- About page, FAQ, privacy statement, cookie notice and terms of use.
- Moderation for administrators, who sign in with a passkey (no passwords).

[Unreleased]: https://github.com/LauLamanApps/phantom-braking/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/LauLamanApps/phantom-braking/compare/v1.0.3...v1.1.0
[1.0.3]: https://github.com/LauLamanApps/phantom-braking/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/LauLamanApps/phantom-braking/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/LauLamanApps/phantom-braking/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/LauLamanApps/phantom-braking/releases/tag/v1.0.0
