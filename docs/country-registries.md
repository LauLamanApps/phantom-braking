# Vehicle registries per country

Reports are only accepted from countries whose **national vehicle register** we can query, so make, model and year
always come from an authoritative source (see "Countries" in the README). This page records what we found for each
country we looked at, so nobody has to redo the research.

Status as of November 2026. Re-check before building anything: access rules change.

| Country | Open plate lookup? | Status |
|---|---|---|
| 🇳🇱 Netherlands | **Yes**: RDW Open Data | ✅ Live (`RdwVehicleRegistry`) |
| 🇩🇪 Germany | No | ⏸ Not reportable. Known as a country, so existing data can be filtered |
| 🇧🇪 Belgium | No | ⏸ Not started |
| 🇫🇷 France | No | ⏸ Not reportable. Known as a country, so existing data can be filtered |

## 🇳🇱 Netherlands: RDW Open Data

- Socrata datasets, no key needed (an optional app token raises rate limits):
  `m9d7-ebf2` (gekentekende voertuigen) and `8ys7-d773` (brandstof).
- Gives make, trade name, first admission date, EU category, **type-approval number** and variant, fuel/hybrid
  class and open recalls. Owner data is not included.
- https://opendata.rdw.nl

## 🇩🇪 Germany

- **No public plate lookup.** Data from the KBA's central register (ZFZR) is only released to authorized bodies, or
  for legal claims arising from road traffic (Halterauskunft via the KBA or the local Zulassungsbehörde).
  [fimportal: Halterauskunft](https://fimportal.de/api/v0/xzufi-services/L100027/105475839/en/pdf)
- **Commercial:** a "KBA car check" on openapi.com, about €0.40 per lookup, returning registration date, model and
  technical data. Its data source and legal basis are **not verified**.
  [openapi.com](https://alpha.openapi.com/products/kba-germany-verification)
- **Official and free, but not a plate lookup:** the KBA publishes **SV 4.2**, *Verzeichnis der Hersteller und Typen*
  (Klasse M, PDF of about 6 MB, edition dated 15 Aug 2026). It maps **HSN/TSN** (fields 2.1 and 2.2 on the
  Zulassungsbescheinigung Teil I) to manufacturer and type.
  [KBA SV 4](https://www.kba.de/DE/Themen/Typgenehmigung/CoC_Daten_Fahrzeugtypdaten/Veroeffentlichungen/SV4.html) ·
  [SV 4.2 PDF](https://www.kba.de/SharedDocs/Downloads/DE/SV/sv42_pdf.pdf?__blob=publicationFile&v=6)
  - Possible design: the reporter enters the plate (only for the fingerprint), HSN/TSN and field B (first
    registration); we resolve make and type from an imported SV 4.2 table. The risky part is parsing the PDF.

## 🇧🇪 Belgium

- **No public plate lookup** from the DIV (Dienst Inschrijving Voertuigen) was found.
- **Commercial:** Impact Software's *Autoconnect LookUp* REST API returns vehicle data, including DIV dates, by
  plate. It requires an API key; price and eligibility are unknown.
  [API docs (PDF)](https://www.inmotiv.be/wp-content/uploads/Autoconnect-LOOKUP_API_REST_xml_json.pdf)
  - Their *WebDIV* JSON service is for requesting plates, not for looking vehicles up.
- MyCarPlate lists Belgium as "API only". Sources and pricing are not verified. https://mycarplate.online/

## 🇫🇷 France

- The **SIV** (Système d'Immatriculation des Véhicules) is restricted. Professionals and authorities get access
  through ANTS; individuals only see their own vehicle.
- **API Particulier, "Extrait d'immatriculation véhicule"**, does return make, commercial name, TVV, first
  registration, energy and emissions, but it is *"à destination exclusive des collectivités dans le cadre de la
  tarification du stationnement résidentiel"* and requires the owner to log in through FranceConnect.
  **A reporting site is not eligible.**
  [catalogue](https://particulier.api.gouv.fr/catalogue/ants/extrait_immatriculation_vehicule)
- data.gouv.fr only has **aggregates** (e.g. registrations per commune). Technical SIV data (blocks 4 and 5) needs a
  *licence à finalité technique*.
  [forum thread](https://forum.data.gouv.fr/t/information-des-vehicules-immatricules/358)
- **Commercial:** plate APIs such as api-plaque-immatriculation.com and CarsXE. These are not official and not
  verified.

## Option: the EU registration certificate (all EU countries)

Since 2004 every EU member state issues registration certificates with the same harmonised field codes
(Directive 1999/37/EC):

| Field | Meaning |
|---|---|
| A | Registration number (plate) |
| B | Date of first registration |
| D.1 | Make |
| D.2 | Type / variant / version |
| D.3 | Commercial description (model) |
| E | VIN |
| K | **EU type-approval number** |

Countries without an open register could let reporters copy these fields from their certificate. Field K is the same
type-approval number the RDW returns, so platforms (and their ADAS hardware) can be compared across countries.

**Trade-off:** this is data *transcribed* from an official document, not *looked up* in a register. It breaks the
current "registry-backed only" rule, so it needs an explicit decision. If it's adopted, store a source marker on the
vehicle snapshot (`registry` vs `certificate`) so statistics can tell the two apart.

## Adding a country

1. Add a `Country` case (`centre()`, `mapZoom()`) and a `country.xx` translation. The country is then known for
   statistics and filters.
2. Implement `CountryVehicleRegistryInterface` in `Vehicle/Infrastructure/Service`. It is tagged automatically, and
   the country becomes reportable.
3. Add the country to the seeder (`app:demo:seed --country=XX`) if you want demo data.
4. Update this page.
