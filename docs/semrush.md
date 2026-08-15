# Semrush SEO Dashboard

A dashboard for the Semrush SEO API (`/semrush`), covering domain overview,
organic keywords, organic competitors, keyword overview, and backlink overview.

The Semrush API key is only ever used server-side and is never exposed to the
frontend, Inertia props, or API responses.

## Setup

1. Add your legacy Semrush API key to the environment:

   ```dotenv
   SEMRUSH_API_KEY=
   SEMRUSH_API_URL=https://api.semrush.com/
   SEMRUSH_CACHE_TTL=3600
   ```

2. The page shows a "not configured" state until the key is present.

Every API call bills Semrush API units, so responses are cached for
`SEMRUSH_CACHE_TTL` seconds (default 3600). Each dashboard view only bills the
first request for a given (report, params) combination.

## Reports

| Report | Semrush type | API path | Units |
| --- | --- | --- | --- |
| Domain Overview | `domain_rank` | `/` | 10 / line |
| Organic Keywords | `domain_organic` | `/` | 10 / line |
| Organic Competitors | `domain_organic_organic` | `/` | 40 / line |
| Keyword Overview | `phrase_this` | `/` | 10 / line |
| Backlink Overview | `backlinks_overview` | `/analytics/v1/` | 40 / request |

The unit balance is read from the free `countapiunits.html` endpoint and cached
for 5 minutes.

### Mobile databases

Reports that support it accept a `device=mobile` parameter, which sends the
`mobile-<database>` value. Keyword research (`phrase_this`) has no mobile
variant and rejects it with an `unsupported_device` error.

### Limits

`limit` defaults to 20 and is capped at 100 (`SEMRUSH_MAX_LIMIT`). Organic
keywords use `display_limit = offset + limit`, which is required for Semrush's
pagination. All limits are configurable in `config/semrush.php`.

## Endpoints

Authenticated (auth middleware), throttled at `60,1`.

| Method | URI | Query params |
| --- | --- | --- |
| GET | `/api/semrush/balance` | — |
| GET | `/api/semrush/domain-overview` | `domain`, `database`, `device` |
| GET | `/api/semrush/organic-keywords` | `domain`, `database`, `device`, `limit`, `offset`, `sort` |
| GET | `/api/semrush/competitors` | `domain`, `database`, `limit`, `offset`, `sort` |
| GET | `/api/semrush/backlinks` | `domain` |
| GET | `/api/semrush/keyword-overview` | `keyword`, `database`, `device` |

Sort values for organic keywords: `position`, `volume`, `traffic`,
`traffic_percent`, `difficulty`, `competition` (prefix `-` for descending).
Competitors: `relevance`, `common_keywords`, `organic_keywords`,
`organic_traffic`.

Domains are normalized (lowercased, scheme/port/path stripped) and validated
with a DNS-style regex before reaching the service.

## Response envelope

Success:

```json
{ "success": true, "message": "", "data": { }, "meta": { } }
```

Error:

```json
{
  "success": false,
  "message": "…",
  "data": null,
  "meta": { "code": "insufficient_units", "retryable": false }
}
```

## Error codes

| Semrush code | HTTP | `meta.code` | Retryable |
| --- | --- | --- | --- |
| `50` | 404 | `no_results` | no |
| `110`, `120`, `402` | 401 | `invalid_api_key` | no |
| `130`, `135` | 403 | `plan_restricted` | no |
| `131`, `134`, `429` | 429 | `rate_limited` | yes |
| `132` | 402 | `insufficient_units` | no |
| `133` | 403 | `database_access_denied` / `backlinks_unavailable` | no |
| `500` | 503 | `semrush_unavailable` | yes |
| `605`, `613`, `1001x` | 422 | `invalid_parameters` | no |

A blank key throws `not_configured` (503) before any request is made. Requests
are not auto-retried (`SEMRUSH_RETRIES=0`) because every attempt bills units.

## Code layout

- `config/semrush.php` — configuration and supported database list.
- `app/Services/Semrush/SemrushService.php` — HTTP client, CSV parsing, column
  mapping, caching, error translation.
- `app/Exceptions/SemrushException.php` — `errorCode()`, `statusCode()`,
  `retryable()`.
- `app/Http/Requests/Semrush/*` — validation and domain normalization.
- `app/Http/Controllers/SemrushController.php` — Inertia page + JSON endpoints.
- `resources/js/pages/Semrush/Index.vue` — dashboard page.
- `resources/js/components/Semrush/*` — search, selectors, metric cards, tables,
  and charts.
- `resources/js/lib/semrushApi.ts` — typed API client (Wayfinder routes).
- `resources/js/composables/useSemrushData.ts` — state and loading per section.
- `tests/Feature/SemrushTest.php` — endpoint/validation/envelope tests.
- `tests/Feature/SemrushServiceTest.php` — HTTP fakes, CSV parsing, caching,
  error translation.

## Notes

- Semrush responses are `;`-delimited CSV with human-readable headers. Rows are
  mapped positionally to the requested `export_columns` codes.
- The backlinks endpoint lives at `/analytics/v1/` and does not accept
  `export_decode` / `export_escape`.
- The API key, raw response bodies, and full error responses are never logged;
  only endpoint, status, error code, and a safe context subset are.
