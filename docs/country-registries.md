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
| 🇳🇴 Norway (EEA) | **Yes**: Statens vegvesen API, free key | 🔜 Best candidate, but the key needs a Norwegian eID |
| 🇬🇧 United Kingdom (not EU) | **Yes**: DVSA MOT History API, free key on approval | 🔜 Feasible; cars without an MOT yet may be missing |
| 🇫🇮 Finland | Web service only; open data has no plates | ❌ No API |
| 🇸🇪 Sweden | Web lookup only; data sold on application | ❌ No open API |
| 🇩🇰 Denmark | Website only (Motorregister) | ❌ No API; existing tools scrape |
| 🇵🇱 Poland | Needs plate **and** VIN **and** first registration date | ❌ Reporters don't know their VIN |
| 🇭🇺 Hungary | Free per person, after a government login | ❌ No API |
| 🇪🇪 Estonia | By contract; plates are not public data by law | ❌ |
| 🇪🇸 Spain | Web service for public administrations only | ❌ |
| 🇨🇿 Czechia | Statistics only; vehicle data for the owner | ❌ |

## Where to go next

1. **Norway** fits the existing design directly: one more `CountryVehicleRegistryInterface` next to
   `RdwVehicleRegistry`, with an authoritative lookup by plate. The blocker is the key: ask Statens vegvesen whether a
   foreign organisation can get one without a Norwegian eID.
2. **United Kingdom**: the MOT History API gives make and model by plate. It is outside the EU, and very new cars
   (no MOT yet) may not be returned. Check that before relying on it.
3. **Everywhere else**, including Germany and France, there is no official open lookup, and that is unlikely to
   change. The options are the EU registration certificate (below), manual entry marked as unverified, or a commercial
   provider (not recommended: unknown sources, cost, and plates sent to a third party).
4. **The EU-wide route**: there is no EU-wide register to query by plate (EUCARIS is for authorities only), but there
   *is* an EU-wide table keyed by **type-approval number**: the EEA's CO2 monitoring data. Combined with field K of
   the registration certificate, it gives make and model for any EU country from an official source. See
   [EU-wide: type approval as the key](#eu-wide-type-approval-as-the-key) below. **Verify the join first.**

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

## 🇳🇴 Norway (EEA, not EU)

- **API for tekniske kjøretøyopplysninger** (Statens vegvesen, Autosys): look up by registration number or chassis
  number. Returns technical data (dimensions, weight, engine and power, brakes, axles, tyres), the date of first
  registration in Norway and EU inspection dates. No owner data and no mileage. Updated daily.
- **Access:** free API key, "open to all private persons and businesses with a Norwegian electronic ID" (BankID,
  Buypass or Commfides); organisations need the Altinn service *Kjøretøyoppslag*. Requests are approved
  automatically. Whether a foreign organisation can get a key is **not stated**: ask.
- The page doesn't list make and model explicitly; the API's response is expected to include them (`merke`,
  `handelsbetegnelse`), but the documentation portal didn't load during this research. **Verify.**
- A separate, **paid** API with owner data exists (from NOK 2,640/month); we don't need it.
- Statens vegvesen explicitly prohibits scraping its web pages.
  [API page](https://www.vegvesen.no/kjoretoy/kjop-og-salg/kjoretoyopplysninger/api-er-for-tekniske-kjoretoyopplysninger/api-for-tekniske-kjoretoyopplysninger/) ·
  [paid API with owner data](https://www.vegvesen.no/fag/teknologi/apne-data/et-utvalg-apne-data/api-for-tekniske-kjoretoyopplysninger-med-eierinformasjon/)

## 🇬🇧 United Kingdom (not EU)

- **DVSA MOT History API**: look up by registration; returns `make`, `model`, `firstUsedDate`, `fuelType`,
  `primaryColour`, and (when known) `registrationDate`, `manufactureDate`, `engineSize`.
- **Access:** register as an individual or organisation; DVSA reviews the request and sends a key within about 5
  working days. Quotas are per key (the old API's guidance: under 150,000 requests a day for single lookups). Cost
  isn't stated. The old API was deprecated on 1 Sep 2025; register for the new one.
- **Caveat:** it is an MOT database. Cars under 3 years old have no MOT yet; the old docs suggest they are still
  returned (with `motTestDueDate`), but this is **not confirmed**. Many phantom-braking cars are new, so test it.
- **DVLA Vehicle Enquiry Service** is the other option, but it returns `make` without `model`, and DVLA is "currently
  not accepting new VES API registrations".
  [MOT API registration](https://documentation.history.mot.api.gov.uk/mot-history-api/register) ·
  [MOT API docs (old version)](https://dvsa.github.io/mot-history-api-documentation) ·
  [DVLA VES](https://developer-portal.driver-vehicle-licensing.api.gov.uk/apis/vehicle-enquiry-service/vehicle-enquiry-service-description.html)

## 🇫🇮 Finland

- Traficom's **open vehicle data** is a bulk CSV of all vehicles in traffic, but without registration numbers or full
  chassis numbers: Traficom treats both as personal data, which open data may not contain.
- A **per-vehicle web service** on Suomi.fi shows technical data for free (with a limit on the number of queries); there
  is no API for it. Restricted, paid data products do include the plate.
  [open data](https://avoindata.suomi.fi/data/en/dataset/ajoneuvojen-avoin-data/resource/00041d67-966e-48cf-a720-255faf09d55c) ·
  [open data FAQ (PDF)](https://tieto.traficom.fi/files/media/file/Avoin%20Data%20Usein%20Kysytyimm%C3%A4t%20Kysymykset.pdf) ·
  [Suomi.fi service](https://www.suomi.fi/services/public-vehicle-data-and-tax-payment-the-finnish-transport-and-communications-agency-traficom/1c5871b0-5a24-4fca-abf4-24c3ea4063a3)

## 🇸🇪 Sweden

- Transportstyrelsen's **Fordonsuppgifter** web lookup by plate is free and needs no login, but it is a web page, not
  an API.
- Data from the road traffic register (*vägtrafikregistret*) is **sold** under conditions, on application with a stated
  purpose. A plate counts as personal data when the owner is a private person.
  [Fordonsuppgifter](https://www.transportstyrelsen.se/sv/vagtrafik/Fordon/Fordonsuppgifter/) ·
  [conditional data](https://www.transportstyrelsen.se/sv/om-oss/statistik-och-analys/oppna-data/villkorad-data-utifran-trafikslag/)

## 🇩🇰 Denmark

- No official API for the **Motorregister** (DMR). Skat delivers it in bulk to Danmarks Statistik, not to the public.
- Community tools (e.g. `dmr.py`) scrape motorregister.skat.dk; not an option for us.
- **Third party:** Synsbasen and Soge.dk offer APIs with most of the DMR data (Soge.dk with a free account and key).
  Their source and terms are **not verified**.
  [Synsbasen API](https://api.synsbasen.dk/v1/docs)
  [dmr.py](https://github.com/j4asper/dmr.py) · [Danmarks Statistik on DMR](https://www.dst.dk/da/Statistik/dokumentation/Times/bilregister-dmr)

## 🇵🇱 Poland

- **Historia Pojazdu** (historiapojazdu.gov.pl) shows make, model, year and first registration, but needs the
  registration number, **VIN** and **date of first registration** together. Reporters won't have the VIN at hand.
- The CEPiK API requires an access certificate, on application.
  [gov.pl: SI CEPiK](https://www.gov.pl/web/cyfryzacja/system-informatyczny-centralnej-ewidencji-pojazdow-i-kierowcow-si-cepik-) ·
  [mubi.pl guide](https://mubi.pl/poradniki/cepik-co-mozna-tam-sprawdzic/)

## 🇭🇺 Hungary

- **JSZP** (Jármű Szolgáltatási Platform) shows a vehicle's history for free by plate, including technical data and
  odometer readings, but each user logs in with the government gateway (Ügyfélkapu). No API. Sources are from
  2019–2023.
  [Pénzcentrum](https://www.penzcentrum.hu/auto/20210904/auto-lekerdezes-ingyen-rendszam-alapjan-mi-mindent-tudhatunk-meg-egy-autorol-1117430)

## 🇪🇪 Estonia

- Under the Traffic Act, register data is public **except** personal data, the registration certificate number, the
  **registration plate** and the VIN. Single lookups by plate (make, commercial name, technical data) exist for
  organisations with a data-exchange contract with Transpordiamet.
  [Liiklusseadus (Riigi Teataja)](https://www.riigiteataja.ee/akt/111082023003.pdf)

## 🇪🇸 Spain

- The DGT has a web service returning make, model, first registration and more by plate, but only through its data
  platform for **public administrations**. A datos.gob.es answer confirms there is no public REST API. Individuals can
  get a free reduced report on the DGT website.
  [DGT data platform](https://sede.dgt.gob.es/en/otros-tramites/tramites-para-administraciones/plataforma-de-intermediacion-de-datos/) ·
  [datos.gob.es request](https://datos.gob.es/en/solicitud-de-datos/api-rest-para-la-obtener-los-datos-de-la-ficha-tecnica-nive-de-un-vehiculo)

## 🇨🇿 Czechia

- **Datová kostka** (dataovozidlech.cz) offers statistics from the vehicle register, not per-vehicle lookups.
  Individual vehicle data is available on request, for the owner.
  [kurzy.cz](https://zpravy.kurzy.cz/626964-ministerstvo-dopravy-zpristupnuje-data-z-registru-vozidel-i-udaje-o-technickych-prohlidkach/) ·
  [portal.gov.cz request](https://portal.gov.cz/sluzby-vs/zadost-o-data-zregistru-silnicnich-vozidel-S7198)

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

## EU-wide: type approval as the key

**No EU-wide plate lookup exists.** EUCARIS, the exchange between national registration authorities, can search by
plate, but only for those authorities; private parties are sent to each country's national contact point.
[EUCARIS](https://www.eucaris.net/services/vat)

**But there is an EU-wide table keyed by type approval.** Under Regulation (EU) 2019/631, every country reports each
year's new car (M1) and van (N1) registrations to the European Environment Agency for CO2 monitoring. The EEA
publishes it per type approval, type, variant and version, per country:

| Field | Meaning |
|---|---|
| `TAN` | Type-approval number, e.g. `e4*2007/46*1293*42` |
| `T`, `Va`, `Ve` | Type, variant, version |
| `Mk` | Make |
| `Cn` | Commercial name (the model) |
| `Ct` | Vehicle category (M1, N1) |
| `MS` | Country of registration |
| `r` | Number of registrations |

- Covers the EU27, Iceland and Norway (the UK until it left), from about 2010. No plates, no VINs: it is not personal
  data.
- Downloads per year (CSV, Access, SQL) and a SQL endpoint (`discodata.eea.europa.eu`). The endpoint answered our
  test query with a 403, so start from the CSV.
- The column names change case between years (`TAN` / `Tan`, `m` / `M`). Parse by a mapping, not by position.
  [2019 schema](https://www.eea.europa.eu/data-and-maps/data/co2-cars-emission-19/co2-emissions-cars-2019-final) ·
  [2025 metadata (PDF)](https://sdi.eea.europa.eu/catalogue/api/records/b4044b06-2e6b-4f8e-a6e6-66e0e98bb0dd/attachments/CO2%20from%20cars%20and%20vans_Stastical%20metadata_2025P.pdf) ·
  [CO2 cars viewer](https://co2cars.apps.eea.europa.eu/1)

**It joins with what we already have.** The RDW returns the same number (`typegoedkeuringsnummer`, e.g.
`e4*2007/46*1293*42` for a Tesla Model Y), and `RdwVehicleRegistry` already maps it to the vehicle snapshot's
`typeApprovalNumber`. Field K on every EU registration certificate is that number as well.

### Possible design

1. **Netherlands: unchanged.** Plate → RDW, which also gives the type approval.
2. **Other EU/EEA countries:** the reporter gives the registration country, the plate (only for the fingerprint), field
   **K** and field **B** (first registration). Make and model come from a local copy of the EEA table, imported once a
   year with a console command. If field K is unknown or not found, fall back to choosing make and model, marked as
   unverified.
3. **One model key everywhere:** group the model pages and statistics by type approval, so a car counts as the same
   model whichever country it was reported from.
4. **Fingerprint per country:** hash the plate together with the registration country, so equal plates in two
   countries don't count as one vehicle.
5. **AEB requirement:** field B gives the first registration date, so the "report it to the ILT" prompt keeps working.
6. Store the source on the vehicle snapshot (`registry`, `certificate+eea`, `manual`), as above.

### Caveats

- Only vehicles registered **new** in the EU/EEA since about 2010, and only M1 and N1. A used import appears under the
  country where it was first registered, which is fine for a lookup by number.
- The same number is written differently across sources (spaces, `*` or `/`, leading `e`). Normalise both sides before
  matching.
- One type approval can cover several variants, and sometimes more than one commercial name. A lookup can return more
  than one model: pick the most registered, or let the reporter choose from that short list.
- **Not verified yet:**
  - that our production type-approval numbers appear in the EEA file;
  - the EEA's reuse terms.

  Download one recent year's CSV and match it against the numbers in our database before building anything.

## Adding a country

1. Add a `Country` case (`centre()`, `mapZoom()`) and a `country.xx` translation. The country is then known for
   statistics and filters.
2. Implement `CountryVehicleRegistryInterface` in `Vehicle/Infrastructure/Service`. It is tagged automatically, and
   the country becomes reportable.
3. Add the country to the seeder (`app:demo:seed --country=XX`) if you want demo data.
4. Update this page.
