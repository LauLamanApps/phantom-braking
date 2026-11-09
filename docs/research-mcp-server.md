# Research MCP server (planned)

**Status: not built.** This page records the design so the work can start from it, and lists what is worth doing
*before* that, because it affects the data being collected today.

## Goal

Give researchers (universities, TNO, consumer organisations, journalists, regulators) **paid** programmatic access to
the phantom-braking data through an [MCP](https://modelcontextprotocol.io) server, so they can query it from an AI
assistant or their own tooling.

**What the fee pays for:** convenience, volume and structure, *not* extra data. See "Data: only what is already public"
below.

## Technology

| Piece | Choice | Notes |
|---|---|---|
| MCP server | [`mcp/sdk`](https://packagist.org/packages/mcp/sdk), the official PHP SDK (PHP Foundation and Symfony) | Pre-1.0 and marked experimental in November 2026 (latest release around 0.9). Expect breaking changes; re-check before starting. [Docs](https://php.sdk.modelcontextprotocol.io/) |
| Symfony integration | [`symfony/mcp-bundle`](https://symfony.com/doc/current/ai/bundles/mcp-bundle.html) | Exposes tools, resources and prompts over HTTP or STDIO |
| Transport | Streamable HTTP | Remote access for researchers; STDIO only for local development |
| Authorization | API key (Bearer) per organisation, later OAuth 2.1 per the MCP spec | Unclear in November 2026: the SDK README lists authorization, but [`omouren/mcp-oauth-bundle`](https://packagist.org/packages/omouren/mcp-oauth-bundle) says the newer MCP authorization spec isn't implemented. Verify when starting. |
| Payment | Mollie (subscriptions and invoices) | Dutch provider, also used in ehbo-systeem |
| Rate limiting and metering | `symfony/rate-limiter` (already installed) plus a usage table | Per key: calls per minute, rows per month |

## Architecture

A new **read-only** module, following the existing DDD layout:

```
src/Research/
├── Domain/
│   ├── Model/              ResearchOrganisation, ApiKey (hashed), Plan, UsageRecord
│   ├── Repository/         …Interface
│   └── Service/            ResearchDatasetInterface: the only door to incident data
├── Infrastructure/
│   ├── Repository/
│   └── Service/            DoctrineResearchDataset (projection, see below), MollieBilling
└── Presentation/
    ├── Mcp/                one class per MCP tool
    ├── Controller/Admin/   organisations, keys, plans, usage (in the existing /admin)
    └── Cli/
```

- **Reuse, don't duplicate.** Tools take the same criteria as the website via `IncidentFilter` (country, make, model,
  road type, period) and build on `IncidentStatisticsInterface`, so the site and the API always agree.
- **One door to the data.** `ResearchDatasetInterface` returns a *research projection* of an incident: a separate
  read model with only the allowed fields. Tools never touch the `Incident` entity directly, so new fields don't leak by accident.
- **Auditing.** Every call is logged per organisation (tool, filter, row count), both for billing and to spot
  re-identification attempts.

### Candidate tools

| Tool | Returns |
|---|---|
| `search_incidents` | Filtered, paginated incident records (research projection) |
| `get_statistics` | Counts per dimension (road situation, trigger, light, consequence, …) for a filter |
| `time_series` | Reports per week or month for a filter |
| `hotspots` | Road segments (country + road number + rounded area) with the most reports |
| `compare_models` | Two or more make/model/type-approval groups side by side, ideally normalised by fleet size |
| `list_values` | Available makes, models, countries and type-approval numbers (for building queries) |

Resources (read-only documents): the field definitions (what each enum means), the methodology text from the site, and
the licence.

## Data: only what is already public

Reporters consented to **publication without plate** and to **research use** (report form and privacy statement). The
API stays within that:

| Field | In the API? |
|---|---|
| Vehicle snapshot (make, model, first-registration year, fuel, type-approval number, variant) | Yes |
| Plate | Never stored, so never available |
| Plate fingerprint | **No**: it would let researchers link reports of the same vehicle across datasets |
| Incident country, road type, situation, road number, speed limit | Yes |
| Coordinates | Rounded at least as much as on the site (about 10 m); coarser in bulk exports (see below) |
| Date and time | Yes, coarsened in bulk exports |
| Severity, speeds, trigger, assist system, weather, light, consequence, recurrence, dealer response | Yes |
| Free-text description | **No** by default: it can contain personal details. Only after moderation, if ever. |
| Footage and photos | Only approved, plate-masked copies, as links. Never originals. |
| Deletion requests, moderation status, admin data | No |

### Re-identification

Researchers can query far more systematically than site visitors. A rare model plus an exact time plus a quiet
rural road can point to one person. Mitigations:

- **Minimum group size** for statistics: suppress or merge cells with fewer than *k* reports (for example *k* = 5).
- **Coarsening in bulk:** date instead of time, about 1 km instead of about 10 m, unless a filter returns large groups.
- **No joins on the vehicle:** no fingerprint (above).
- **Licence terms** forbid re-identification attempts and linking with other datasets about individuals.
- **Audit log** to spot suspicious query patterns.

## Access and billing

- **Organisation → API keys** (shown once, stored hashed), each with a **plan** (for example academic, commercial).
- **Rate limits** per key, plus a monthly row or call quota per plan, metered in a usage table.
- **Billing** through Mollie subscriptions; invoices for organisations.
- **Admin** (existing `/admin`): manage organisations, keys, plans and usage, and revoke a key immediately.

## Legal

- **Data licence** for researchers: allowed use, attribution, no re-identification or individual profiling, no
  resale of raw data.
- **API terms** next to the existing terms of use.
- **Privacy statement:** add researchers (paying organisations) as recipients of the published, plate-less data.
- **DPIA:** consider one. Systematic sharing of location data with third parties is the kind of processing it's meant for.

## Worth doing now

These affect the data collected *before* the server exists:

1. **Fleet sizes for normalisation.** "Reports per 100,000 vehicles of this model" is the first thing researchers will
   ask, so popular brands don't look worse just by volume. The RDW publishes registered vehicle counts per make and model; storing
   periodic snapshots of those counts now makes historical normalisation possible later.
2. **Explicit consent wording.** The report form says data is "gebruikt voor onderzoek" (used for research). Saying
   explicitly that published, plate-less data may also be **shared with researchers, including for a fee**, avoids
   debate later. Changing the text now is cheaper than asking existing reporters again.
3. **Keep the research projection in mind** when adding new incident fields: decide per field whether it is public
   (and thus API material) or internal.

## Open questions

- Pricing model: subscription per organisation, per call, or free for academia and paid for commercial use?
- Which *k* and which coarsening levels? Decide together with a privacy expert.
- Do approved masked media belong in the API, or only on the website?
