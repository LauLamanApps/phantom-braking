# Changelog

All notable changes to this reporting point. Versions follow [Semantic Versioning](https://semver.org); the format is
based on [Keep a Changelog](https://keepachangelog.com).

## [Unreleased]

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

[Unreleased]: https://github.com/LauLamanApps/phantom-braking/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/LauLamanApps/phantom-braking/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/LauLamanApps/phantom-braking/releases/tag/v1.0.0
