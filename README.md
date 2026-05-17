# Sentinel — Spending Card Authorization Service

A Symfony 6.4 service that simulates a card-processor authorization platform:
issues virtual spending cards, evaluates inbound authorization requests against
per-card rules, and fans the resulting events out to downstream subscribers via
an SQS-triggered Lambda.

![PHP](https://img.shields.io/badge/php-8.3-777bb4) ![Symfony](https://img.shields.io/badge/symfony-6.4_LTS-000000) ![Doctrine](https://img.shields.io/badge/doctrine_orm-3.x-fc6a31) ![PHPStan](https://img.shields.io/badge/phpstan-level_8-1099c0) ![Tests](https://img.shields.io/badge/tests-153_passing-2ea44f) ![OpenAPI](https://img.shields.io/badge/openapi-3.1-6ba539)

> **Reviewer shortcut**: `make bootstrap`, then open
> <http://localhost:8100/docs> — full OpenAPI reference with a built-in
> interactive console. The page signs the inbound authorization request
> with HMAC for you. Run `make check` to see the **153 tests** pass.

## What this is

A work sample built to demonstrate Domain-Driven Design, hexagonal
architecture, and the async/reliability patterns that show up in any
real authorization platform: the outbox pattern, two-layer idempotency,
optimistic locking, HMAC-signed webhooks, and retry-with-DLQ for outbound
delivery.

The domain is inspired by patient travel reimbursement debit cards from
clinical trials. Each card carries spending limits, an allowed merchant
category list, and a lifecycle state machine (Pending → Active →
Suspended/Closed). The card processor is simulated by a synchronous
inbound HTTP request that returns the approve/decline decision;
downstream consumers receive outbound webhooks describing every
authorization outcome.

Architecture and engineering discipline are the things this codebase
optimizes for. Feature breadth was deliberately constrained — every
feature included is exercised end-to-end with tests at the appropriate
level (unit / integration / functional).

## What this is NOT

This is **not** production payment software. There is no PCI scope, no
real card processor integration, no PHI/HIPAA controls, no fraud
detection, no multi-region story, and no real key management. The card,
authorization, and webhook flows are realistic in shape but the data
they handle is fabricated. **Do not** put real cardholder data into
this service.

## Architecture diagram

```
              ┌──────────────────────────────────────────────────────┐
              │  Card Processor (simulated)                          │
              └──────────┬───────────────────────────────────────────┘
                         │ POST /api/authorizations
                         │ X-Processor-Signature: t=…,v1=…
                         ▼
   ┌────────────────────────────────────────────────────┐      ┌──────────────────┐
   │  AuthorizeCardController  (200ms budget)           │      │ Redis            │
   │     1. verify HMAC signature                       │ ◀──▶ │ (idempotency     │
   │     2. idempotency cache lookup                    │      │  cache, 24h TTL) │
   │     3. AuthorizeCardCommandHandler                 │      └──────────────────┘
   └──────────┬─────────────────────────────────────────┘
              │
              │  one transaction
              ▼
   ┌──────────────────┐   ┌──────────────────┐   ┌─────────────────┐
   │  cards (Postgres)│   │ authorizations   │   │ outbox_events   │
   └──────────────────┘   └──────────────────┘   └────────┬────────┘
                                                          │
                                            FOR UPDATE SKIP LOCKED
                                                          │
                                                          ▼
                                          ┌──────────────────────────┐
                                          │ worker-outbox (PHP CLI)  │
                                          └────────┬─────────────────┘
                                                   │ SendMessage
                                                   ▼
                                          ┌──────────────────────────┐
                                          │ SQS  (outbound queue)    │
                                          │   └─ DLQ (5 attempts)    │
                                          └────────┬─────────────────┘
                                                   │ event-source map
                                                   ▼
                                          ┌──────────────────────────┐
                                          │  Lambda (Node 22 / TS)   │
                                          │  POST subscriber.url     │
                                          │  X-Webhook-Signature: …  │
                                          └──────────────────────────┘
```

The inbound path is a synchronous Symfony controller, on a strict 200ms
P95 budget. Everything cross-aggregate or downstream is async, behind
the outbox.

## Prerequisites

- **Docker** with Compose **v2** (the Makefile uses `docker compose`, not
  `docker-compose`). Docker Desktop on macOS/Windows or a recent
  `docker-ce` on Linux both work.
- **GNU Make** (`brew install make` on macOS; preinstalled on most Linux).
- **A Unix-style shell** (macOS, Linux, or WSL2 on Windows). The bootstrap
  target uses Bash idioms; native Windows `cmd`/PowerShell won't run it.
- **Free TCP ports**: only **8100** (API) and **8101** (mock-receiver) are
  bound to the host. Postgres, Redis, and floci stay inside the compose
  network so they don't collide with anything you already run locally
  (Homebrew postgres on 5432, redis on 6379, etc.). To poke at them
  anyway, use `docker compose exec` — see "Reset to a clean slate" below.
- **~2 GB free disk** for the Docker images on first run.

No host PHP, Composer, Node, or database is required — everything is
inside containers.

## Quick start

Everything runs in Docker; no host PHP is required.

```bash
make bootstrap
```

That's it. The target builds images, starts every container, installs
PHP dependencies, applies migrations, and brings the outbox worker up
in the correct order (it boots before migrations and exits when the
`outbox_events` table is missing — the bootstrap handles that race for
you). Total time on a fresh machine: a couple of minutes on first run,
~15 seconds thereafter.

If you'd rather run the steps individually, the same flow is:

```bash
make up         # build + start containers
make install    # composer install inside the app container
make migrate    # apply Doctrine migrations
docker compose up -d worker-outbox   # only needed on first boot
```

Once bootstrap finishes, three URLs are live:

| URL | Purpose |
|---|---|
| <http://localhost:8100/docs> | **Interactive API console** (OpenAPI 3.1 + Scalar). Recommended entry point. |
| <http://localhost:8100> | The Symfony app itself |
| <http://localhost:8101> | Mock subscriber — captured outbound deliveries echo here |

A `docker compose ps` will also show four background containers with no
reviewer-facing UI and no host port: `postgres`, `redis`, `floci`
(LocalStack-compatible AWS emulator), and **`worker-outbox`** — the
long-running PHP CLI that drains `outbox_events` → SQS → Lambda. A
one-shot `lambda-builder` will appear as "exited" after bundling the
Lambda zip; that's expected.

The AWS resources floci provisions (SQS queue, DLQ, Lambda function,
event-source mapping) are wired up by `infra/init/setup.sh` on
container ready-state.

### Reset to a clean slate

To wipe everything (containers, volumes, postgres data, Redis state,
built images) and start over:

```bash
docker compose down -v --remove-orphans   # containers + volumes + network
docker rmi sentinel-app sentinel-worker-outbox   # force a Dockerfile rebuild
make bootstrap                            # back up from scratch
```

`make down` (without args) only stops containers and keeps your data —
useful between sessions; `down -v` is the full nuke.

To peek at the internal services that aren't exposed to the host:

```bash
docker compose exec postgres psql -U app cards    # inspect tables
docker compose exec redis redis-cli               # inspect idempotency cache
docker compose exec floci awslocal sqs list-queues   # inspect SQS state
```

## How to read this repo

A 15-minute reading path, ordered to walk a single authorization request from
the domain outward and then through the async tail. Each step is one file.

1. **[`backend/src/Domain/Card/Card.php`](./backend/src/Domain/Card/Card.php)** —
   The rich aggregate. Read `authorize()` and the rules engine; note that it
   returns an `AuthorizationResult` rather than raising events (see
   [ARCHITECTURE.md §5](./ARCHITECTURE.md#5-aggregates-card-and-authorization)
   for why).
2. **[`backend/src/Application/Card/AuthorizeCardCommandHandler.php`](./backend/src/Application/Card/AuthorizeCardCommandHandler.php)** —
   Orchestration. Loads the aggregate, calls the domain, persists the
   `Authorization` and outbox events in one transaction.
3. **[`backend/src/Http/Controller/AuthorizeCardController.php`](./backend/src/Http/Controller/AuthorizeCardController.php)** —
   Transport. HMAC verify → Redis idempotency lookup → handler → cache the
   response. The 200 ms hot path lives here.
4. **[`backend/tests/Functional/AuthorizeCardTest.php`](./backend/tests/Functional/AuthorizeCardTest.php)** —
   End-to-end proof. Real HTTP through the kernel; signature, idempotency,
   and the full decision chain all exercised.
5. **[`backend/src/Infrastructure/Outbox/OutboxPublishCommand.php`](./backend/src/Infrastructure/Outbox/OutboxPublishCommand.php)** —
   The async tail. `SELECT … FOR UPDATE SKIP LOCKED`, dispatch to SQS, mark
   published — all in one transaction so a SendMessage failure rolls back
   cleanly.
6. **[`lambda/src/index.ts`](./lambda/src/index.ts)** — The outbound Lambda.
   POSTs the signed delivery; reports per-message failures via SQS's
   partial-batch response so one bad subscriber doesn't fail the batch.

For the deeper architectural narrative — bounded contexts, reliability
patterns, rejected alternatives — read [ARCHITECTURE.md](./ARCHITECTURE.md).

## Exercising the API

### Interactive console

Open **<http://localhost:8100/docs>** for the full OpenAPI 3.1
reference with a built-in "Try it out" console.

- The page signs `POST /api/authorizations` with HMAC-SHA256 in the
  browser at send time — reviewers don't need a curl one-liner to
  exercise the signed endpoint.
- A sticky settings banner exposes the `X-API-Key` and processor
  HMAC secret, pre-filled with the `.env` dev defaults
  (`dev_admin_key`, `dev_processor_signing_secret`). Change them in
  place to test the rejection paths.

Spec source: [`backend/openapi.yaml`](./backend/openapi.yaml) — a single
hand-written file. A functional test
(`tests/Functional/OpenApiSpecTest`) guards against drift: every
routed endpoint must be in the spec, and the `IssueCard` 201
response shape must match what the controller returns.

### Or use curl

```bash
# 1. Issue a card and capture its id.
CARD_ID=$(curl -s -X POST http://localhost:8100/api/cards \
  -H 'X-API-Key: dev_admin_key' \
  -H 'Content-Type: application/json' \
  -d '{
    "cardholder_id": "01890d3a-3e95-7000-8000-1234567890ab",
    "spending_limits": {"per_transaction": 50000, "daily": 200000, "monthly": 1000000},
    "initial_balance": 100000,
    "currency": "USD",
    "allowed_merchant_categories": ["4121", "5812"]
  }' | jq -r .id)

# 2. Activate it.
curl -X POST "http://localhost:8100/api/cards/$CARD_ID/activate" \
  -H 'X-API-Key: dev_admin_key'

# 3. Authorize a transaction. The inbound POST /api/authorizations
#    request is HMAC-signed — see the "Inbound authorization example"
#    section below for the full payload and signing dance.
```

## Key design decisions

- **Hexagonal layering, enforced by tests**: `Domain/` has zero
  framework dependencies (`grep -r "use Symfony" backend/src/Domain/`
  returns nothing). Doctrine mapping lives in XML under
  `Infrastructure/Persistence/Doctrine/Mapping/` so no ORM attributes
  bleed into the domain.
- **Two aggregates, not one**: `Card` and `Authorization` are
  separate. They have different lifecycles (mutable vs. immutable),
  different retention requirements, and different transactional
  scope. They communicate through the outbox.
- **Outbox pattern with `FOR UPDATE SKIP LOCKED`**: domain events are
  written to `outbox_events` in the same transaction as the aggregate
  save. A background worker drains the table with skip-locked semantics
  so multiple workers can scale horizontally without coordination.
- **Two-layer idempotency**: a Redis cache (keyed by `processor_auth_id`)
  is the latency-budget fast path; a unique constraint on
  `authorizations.processor_auth_id` is the durable backstop. The
  cached response is the exact JSON the first call produced, so the
  processor's retries always see the same envelope.
- **Asymmetric runtime split**: outbound webhook delivery runs as a
  Node Lambda consuming an SQS queue (emulated locally via
  [floci](https://github.com/floci-io/floci)) so retry, dead-lettering,
  and horizontal scale are managed primitives. Inbound stays a Symfony
  controller — Lambda cold starts are too unpredictable for the 200 ms
  inbound budget. See [ARCHITECTURE.md](./ARCHITECTURE.md) for the full
  rationale and the alternatives that were rejected.
- **Optimistic locking, not pessimistic**: the `Card` aggregate carries
  a `version` field; Doctrine increments it on UPDATE and the WHERE
  clause guards against lost-update races. No `SELECT … FOR UPDATE`
  on the hot path.

## Project structure

```
sentinel/
├── backend/                 Symfony 6.4 service (PHP 8.3, Doctrine 3)
│   ├── src/
│   │   ├── Domain/          Pure domain. Zero framework imports.
│   │   │   ├── Card/        Card aggregate, rules engine, state machine
│   │   │   ├── Authorization/  Decision record, reversal lifecycle
│   │   │   ├── Merchant/    Identity-less merchant value objects
│   │   │   ├── Money/       Money + Currency, exact-amount integers
│   │   │   └── Shared/      UUID v7, Identifier, AggregateRoot, DomainEvent
│   │   ├── Application/     Orchestration. Ports defined here.
│   │   │   ├── Card/        Command handlers, queries, view DTOs
│   │   │   ├── Outbox/      OutboxRepository + OutboxReader ports
│   │   │   ├── Webhook/     Subscriber registry, delivery, dispatcher port
│   │   │   ├── Idempotency/ IdempotencyStore port
│   │   │   └── Shared/      Clock + TransactionManager ports
│   │   ├── Infrastructure/  Adapters that fulfill the ports
│   │   │   ├── Persistence/Doctrine/   Repositories, custom types, XML mapping
│   │   │   ├── Idempotency/            Redis-backed cache
│   │   │   ├── Outbox/                 app:outbox:publish worker command
│   │   │   ├── Webhook/                HMAC verifier + SQS dispatcher
│   │   │   └── Clock/                  SystemClock
│   │   └── Http/            Thin transport layer
│   │       ├── Controller/  One file per route, __invoke handlers (incl. DocsController)
│   │       ├── Request/     Request parsing + JSON validation
│   │       ├── Resources/   api-docs.html (Scalar wrapper + HMAC signing interceptor)
│   │       ├── Security/    ApiKeyAuthenticator (two virtual users)
│   │       ├── EventListener/  ExceptionSubscriber → JSON error envelope
│   │       └── Exception/   InvalidRequestException
│   ├── openapi.yaml         OpenAPI 3.1 spec (source of truth, served at /openapi.yaml)
│   ├── tests/
│   │   ├── Unit/            Pure unit tests, no container
│   │   ├── Integration/     KernelTestCase + real Postgres/Redis via DAMA
│   │   └── Functional/      WebTestCase HTTP round-trips (incl. OpenApiSpecTest drift guard)
│   ├── config/packages/     Symfony bundle config (doctrine, security, subscribers)
│   └── migrations/          Doctrine migrations
├── lambda/                  Outbound delivery Lambda (Node 22, TypeScript)
│   └── src/index.ts         SQS handler with partial-batch failure reporting
├── infra/
│   └── init/setup.sh        floci ready-state init: SQS queue + DLQ + Lambda
├── compose.yaml             All services
├── Makefile                 bootstrap / up / install / migrate / smoke / test / phpstan / cs / check
├── scripts/
│   └── smoke.sh             End-to-end script: issue → activate → authorize → verify fan-out
├── ARCHITECTURE.md          The deep architectural walkthrough
└── README.md                You are here
```

## Testing

```bash
make smoke        # end-to-end: issue → activate → authorize → fan-out (~25s)
make test         # full PHPUnit run inside the app container
make phpstan      # static analysis at level 8
make cs           # PHP-CS-Fixer dry-run + diff
make check        # phpstan + cs + test
```

`make smoke` exercises the full pipeline against the running stack with
real HTTP, real Postgres, real Redis, real SQS, and a real Lambda invocation;
the assertion at the end confirms the outbound webhook actually arrived at
the mock receiver. It's the one-line way to convince yourself the wiring
holds end-to-end after a code change. See [`scripts/smoke.sh`](./scripts/smoke.sh).

> `make test` expects the `cards_test` database to exist; `make bootstrap`
> provisions it. If you bypassed bootstrap and ran the granular targets,
> create it manually: `docker compose exec app php bin/console
> --env=test doctrine:database:create --if-not-exists` followed by the
> `--env=test` migration.

Three test suites with distinct intent:

| Suite | Location | What it covers |
|---|---|---|
| **Unit** | `tests/Unit/` | Domain rules, value-object invariants, and application orchestration with in-memory fakes. Runs in milliseconds. |
| **Integration** | `tests/Integration/` | Adapters wired against real Postgres + Redis. Each test runs inside a DAMA-managed transaction that rolls back, so they stay independent and the database stays clean. |
| **Functional** | `tests/Functional/` | HTTP round-trips through the kernel. Auth, signature verification, idempotency, and the full authorization controller chain. |

CI runs all three on every push (see `.github/workflows/ci.yml`).

## Inbound authorization example

`POST /api/authorizations` is signed with HMAC-SHA256. The
header is `X-Processor-Signature: t=<unix>,v1=<hex-hmac>`, signing the
canonical message `<unix>.<request-body>`. A 5-minute clock skew is
tolerated; outside that window the request is rejected.

> The `/docs` console (above) signs this endpoint for you in-browser.
> The example below is for terminal use.

```bash
BODY='{
  "processor_auth_id": "auth_abc123",
  "card_id": "'"$CARD_ID"'",
  "amount": 5000,
  "currency": "USD",
  "merchant": {
    "name": "Uber",
    "category_code": "4121",
    "location": {"city": "Boston", "region": "MA", "country": "US"}
  },
  "requested_at": "'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'"
}'
TS=$(date +%s)
SECRET=dev_processor_signing_secret   # value of PROCESSOR_SIGNING_SECRET
SIG=$(printf '%s.%s' "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -r | cut -d' ' -f1)

curl -X POST http://localhost:8100/api/authorizations \
  -H "X-Processor-Signature: t=$TS,v1=$SIG" \
  -H 'Content-Type: application/json' \
  -d "$BODY"
```

Approved response:

```json
{
  "authorization_id": "01HQXYZ...",
  "status": "approved",
  "decline_reason": null
}
```

Declined response:

```json
{
  "authorization_id": "01HQXYZ...",
  "status": "declined",
  "decline_reason": "INSUFFICIENT_FUNDS"
}
```

Decline reasons are documented as a closed set on
`App\Domain\Authorization\DeclineReason`.

## Outbound webhook example

After an approved authorization, the outbox worker fans the event out
to every active subscriber listed in `backend/config/packages/subscribers.yaml`.
Each delivery is sent to SQS, picked up by the Lambda, signed, and
POSTed at the subscriber URL.

The default config points both subscribers at the `mock-receiver`
service (`mendhak/http-https-echo`), which echoes every request back.

```bash
# Watch the captured deliveries in real time.
docker compose logs -f mock-receiver

# Or hit the receiver's web UI.
open http://localhost:8101/
```

Each outbound POST carries:

| Header | Meaning |
|---|---|
| `X-Webhook-Signature` | `t=<unix>,v1=<hex-hmac>` over `<unix>.<body>` |
| `X-Webhook-Event-Id`  | The outbox `event_id` (idempotency key for the subscriber) |
| `X-Webhook-Event-Type`| e.g. `card.authorization.approved` |
| `X-Webhook-Delivery-Id` | The per-subscriber delivery row id |

Subscribers should verify the signature, dedupe on `X-Webhook-Event-Id`,
and return any 2xx to acknowledge. Non-2xx (or no response within 10s)
causes the Lambda to fail that batch item; SQS retries up to 5 times,
then routes to the dead-letter queue.

## What I'd do next

If this graduated from work sample to production deployment, the next
ordered steps would be:

1. **Lambda → backend feedback loop**: currently
   `webhook_deliveries.status` captures only the dispatch event. A real
   system needs the Lambda to update the row on completion (either with
   direct Postgres access through RDS Proxy, or a "delivery completed"
   event posted back through EventBridge). Without this, the admin
   replay endpoints can't accurately distinguish "in flight" from
   "permanently failed".
2. **Admin replay + observability**: `GET /api/admin/webhook-deliveries?status=failed`
   and `POST /api/admin/webhook-deliveries/{id}/replay`, paired with
   CloudWatch metrics + alarms on DLQ depth, invoke failures, and
   throttles. Deferred for this work sample; meaningful only after
   step 1 lands.
3. **HIPAA + key management**: the service is HIPAA-aware in shape
   (audit logging, encrypted-at-rest assumptions) but stores no real
   PHI. Production needs KMS-rooted secrets, audit-log destinations
   that can't be tampered with by the application role, and
   encryption-in-transit between every hop.
4. **Real IaC**: Terraform/CDK targeting real AWS, with a CI pipeline
   that packages and deploys the Lambda independently of the backend.
   The `awslocal` calls in `infra/init/setup.sh` map one-to-one to the
   AWS API calls that IaC would emit.
5. **Dynamic subscriber management**: subscribers come from a static
   YAML today. Production wants them in a database with an admin UI,
   per-subscriber rate limits, exponential back-off policies, and the
   ability to disable a misbehaving subscriber without a deploy.

See [ARCHITECTURE.md](./ARCHITECTURE.md) for the deeper architectural
discussion: bounded contexts, why the aggregates split this way, the
async/reliability patterns, and the trade-offs that drove specific
design calls.
