# Teztarif SaaS architecture

## Baseline

The supported baseline is QloApps `v1.8.x` (PHP 8.2–8.5 and MySQL 5.7–8.4). The repository default branch must not remain on `v0.9` for a production deployment.

QloApps multishop and profile permissions are useful building blocks, but they are not a complete SaaS boundary. Hotel tables use `id_hotel` and booking/order tables use a mix of `id_hotel` and `id_shop`; therefore a shop alone cannot prove company ownership for every hotel record.

## SaaS control module

`modules/teztarifsaas` adds:

- company workspaces linked one-to-one with a QloApps shop;
- Basic, Pro and Max plans;
- subscription/trial state and external payment-provider identifiers;
- plan module entitlements and limits;
- one-owner property-to-company mapping;
- backend guards for subscription, licensed modules and request-scoped hotel/order IDs;
- append-only audit events with hashed IP addresses;
- per-company AI request/token/cost counters;
- evidence-backed Smart Operations recommendations.

The module retains business/audit data when uninstalled. Deleting tenant records must be a separate, reviewed retention workflow.

## Roles and permissions

- SaaS Super Admin: QloApps Super Admin plus all SaaS Control screens.
- Company Admin: employee associated only with its shop, a company-admin profile and explicitly mapped properties.
- Team roles: existing QloApps Profiles and Access permissions. Navigation follows the same tab permissions; backend checks remain authoritative.

Every company employee must be associated with exactly one tenant shop. Sharing a profile across unrelated companies is not recommended because hotel access is profile-based in QloApps core.

## Practical AI only

The first release intentionally uses auditable rules for high-impact alerts:

1. current/future overbooking risk;
2. upcoming bookings without a room assignment;
3. outstanding balances before arrival.

These alerts have explicit evidence and never take autonomous action. Later ML features should be enabled only after historical data quality and offline accuracy are measured:

- occupancy/demand forecasting for staffing and purchasing;
- cancellation/no-show risk with reminder or deposit suggestions;
- price recommendations with human approval and floor/ceiling rules;
- duplicate booking and payment anomaly detection;
- review/complaint prioritization with source text and confidence;
- guest-ID/invoice OCR with consent, encryption and retention limits.

Generic chat, auto-generated marketing text and automatic price changes are not part of the production path because they do not justify their cost/risk without a measurable workflow outcome.

## Remaining production gates

Before enabling multiple unrelated paying companies in one database:

1. add immutable tenant ownership to every hotel-domain table or prove coverage through an equivalent database policy layer;
2. update every hotel/booking/report/export/webservice query to enforce that ownership;
3. add cross-tenant denial tests for list, view, create, update, delete, import, export, attachment, cache and background-job paths;
4. implement webhook-verified Razorpay/Stripe billing with idempotency and transactional subscription updates;
5. add 2FA, session/device management and password-reset links (the current email-password reset flow must be replaced);
6. run full MySQL integration, browser E2E, backup/restore and mobile/desktop tests.

Until these gates pass, use one database/deployment per unrelated company or limit the shared instance to a single trusted hotel group.
