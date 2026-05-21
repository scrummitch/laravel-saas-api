# Laravel SaaS API — Code Sample

> **This repository is a code sample / portfolio snapshot.** It mirrors a point-in-time view of the production API for [Plandalf](https://plandalf.com), a SaaS pricing, paywall, and checkout platform. It is **not** the canonical source — it is published here so I can share a representative example of my Laravel work without sending people to private infra.
>
> Author: [Mitchell Flindell](https://github.com/scrummitch)
>
> History has been squashed to a single commit. For an honest impression of the codebase, read the source — not the commit log.

---

## What this codebase does

Plandalf is a SaaS billing / pricing / paywall platform. The API powers:

- **Catalog** — products, features, plans, inclusions, packages, offerings
- **Pricing** — charge configurations, tiered/usage pricing, currency handling via `moneyphp/money`
- **Billing** — Stripe integration (subscriptions, invoices, payment intents, webhooks), import + reconciliation
- **Convert** — paywall workflows, signals, and conversion logic
- **Checkout** — multi-step checkout commit + attribution
- **Usage** — metric definitions and event ingestion
- **Multi-tenancy** — organization-scoped resources, Sanctum personal access tokens, per-org API keys

## Stack

- PHP 8.3 / Laravel 11
- Sanctum (API auth), Sentry, Stripe PHP SDK, GeoIP2
- Queues, jobs, observers, listeners, policies, form requests, API resources
- PHPUnit (feature + unit), Larastan, Pint
- Docker (FPM + worker), GHCR, AWS ECS deploy pipeline

## Notable modules to look at

| Area | Path |
| --- | --- |
| Domain layout | `app/Billing`, `app/Catalog`, `app/Convert`, `app/Pricing`, `app/Store` |
| Stripe integration | `app/Services/Billing`, `app/Jobs/*Stripe*` |
| Checkout commit pipeline | `app/Services/Checkout/CommitCheckoutService.php` |
| Policies | `app/Policies/` (24 policies) |
| Form requests | `app/Http/Requests/` (~40 requests) |
| Feature tests | `tests/Feature/` |
| Deploy pipeline | `.github/workflows/push.yml`, `task-definition.json`, `Dockerfile` |

## Original project notes

(Preserved from the upstream README for context.)

### Modules

- **Account / Management** — org and user surfaces
- **Catalog** — Products are purchasable SKUs which include sets of Features. A Feature is a singular usable attribute of a product.
- **Plan** — A set of prices for products; acts as a contract between an org and a customer.
- **Inclusion** — A set of features and limits included in a plan.
- **Package** — Bundling of multiple products at a specific price point (e.g. sm/md/lg/xl).
- **Offering** — A set of packages at specified price points available together at a point in time.
- **Billing / Charge** — A price is a charge configuration for a product.
- **Usage / Metric / Event** — Measures product usage; events record interactions with features.
- **Convert / Workflow / Paywall** — Paywall barriers and the listeners that trigger them.
- **Checkout** — Workflow for purchasing products.

### References

- https://shopify.dev/docs/api/admin-graphql/2024-01/mutations/appsubscriptioncreate
- https://stripe.com/docs/api/payment_intents/create#create_payment_intent-capture_method
