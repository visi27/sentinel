# Architecture

A walkthrough of the architectural decisions in this service, what they
do, and where they intentionally deviate from the original spec.

## Monorepo layout

```
sentinel/
├── backend/  Symfony service (PHP 8.3 / Symfony 6.4 LTS / Doctrine ORM 3)
├── lambda/   Outbound webhook delivery Lambda (Node 22 / TypeScript)
├── infra/    Local AWS emulation via floci (init script for SQS + Lambda)
├── compose.yaml  Orchestrates app + postgres + redis + floci + worker + mock-receiver
├── Makefile      Repo-wide commands
└── .github/      CI (runs with working-directory: backend)
```

## Bounded contexts inside the backend

Even though everything lives in one service, the code is organized
around three contexts:

- **Card** — the card aggregate, its lifecycle state machine, the
  authorization rules engine, and the read-side `CardView`.
- **Authorization** — the immutable record of a decision. Separate
  aggregate from Card because retention, transactional scope, and
  reversal lifecycle differ.
- **Merchant** — the value-object context for `Merchant`,
  `MerchantCategoryCode`, `GeoLocation`. No aggregate of its own;
  merchants are identity-less data on authorizations.

The `Domain/Shared/` directory carries the type-system primitives:
pure-PHP UUID v7, the `Identifier` base, `AggregateRoot`, the
`DomainEvent` interface and `AbstractDomainEvent` base, and the root
`DomainException`.

## Layering

Hexagonal: dependencies point inward, never outward.

```
Http ──▶ Application ──▶ Domain
              │
              ▼
       (ports / interfaces)
              │
              ▼
       Infrastructure (Doctrine, Redis, async-aws/sqs, etc.)
```

- **Domain** has zero framework dependencies. `grep -r "use Symfony"
  backend/src/Domain/` returns nothing. Mapping is XML in
  `backend/src/Infrastructure/Persistence/Doctrine/Mapping/` so no
  Doctrine attributes touch domain code.
- **Application** orchestrates: command handlers load aggregates, call
  domain methods, persist, dispatch events through the outbox. Owns
  ports like `Clock`, `TransactionManager`, `OutboxRepository`,
  `IdempotencyStore`, query-side services, and the new
  `OutboundWebhookDispatcher` / `WebhookDeliveryRepository`.
- **Infrastructure** implements every port. Custom Doctrine types
  bridge value-object identifiers; JSONB columns hold `Merchant` and
  the MCC list; Redis backs idempotency; async-aws sends to SQS.
- **Http** is thin: controllers parse, delegate, serialize. One
  `ExceptionSubscriber` translates every domain / transport /
  validation exception to the shared `{error: {code, message}}`
  envelope.

## Why aggregates are split this way

Card and Authorization are separate aggregates because they have:

- **Different lifecycles** — a Card is mutable (state transitions,
  balance changes); an Authorization is immutable after creation
  except for reversal.
- **Different transactional scope** — the authorization flow saves an
  Authorization unconditionally (audit) but the Card only when
  approved.
- **Different retention** — authorizations outlive cards in compliance
  systems. Cardholders close cards; the audit trail stays.

They communicate via the outbox: `Card::authorize()` returns an
`AuthorizationResult`, and the application service creates an
`Authorization` aggregate from it. Events flow in one direction.

## Async + reliability patterns

### Outbox

Domain events are recorded on the aggregate (`AggregateRoot::raise()`)
and released after persistence. The application service writes them to
the `outbox_events` table inside the same transaction as the aggregate
save — eliminating the dual-write problem.

The outbox worker (`bin/console app:outbox:publish`) drains unpublished
rows with `SELECT ... FOR UPDATE SKIP LOCKED`, fans them out to every
listening subscriber, and marks each row published. Multiple workers
can run concurrently without conflict.

### Idempotency

Two layers:

1. **Fast path**: Redis keyed by `processor_auth_id`. The webhook
   controller checks before invoking the handler; on a hit it returns
   the cached JSON response verbatim. This protects the latency budget.
2. **Durable backstop**: a unique constraint on
   `authorizations.processor_auth_id` rejects duplicates even under a
   race. The handler also pre-checks the repository.

### Retry and dead-lettering (outbound)

SQS handles delivery retry: if the Lambda throws or returns
`batchItemFailures`, the message stays on the queue until the receive
count is exhausted, then moves to the dead-letter queue. The Lambda
uses partial-batch reporting so a single bad message doesn't poison the
whole batch.

## Deliberate spec deviation — Lambda for outbound delivery

The spec (Section 10.5) describes outbound webhook delivery as a
Symfony Messenger console worker. This implementation instead runs the
delivery as an **AWS Lambda function consuming an SQS queue**, emulated
locally by [floci](https://github.com/floci-io/floci).

**Scope of the deviation**: only the outbound delivery path. The
inbound webhook (`POST /api/webhooks/authorization`) remains a Symfony
controller — it's on the strict 200ms latency budget, and Lambda cold
starts are too unpredictable there.

**Why**: the user explicitly opted in to "expand the system complexity"
to demonstrate a multi-runtime, queue-driven architecture. The trade-
off is one extra language (TypeScript), one extra container (floci),
and an extra deployable (`lambda/`). The boundary is intentionally
narrow so the deviation is contained.

**How it works locally**:

1. `compose up` starts `lambda-builder` (one-shot Node container that
   bundles `lambda/src/index.ts` into `dist/handler.zip`).
2. `floci` starts, runs `infra/init/setup.sh` on ready state, which
   creates the SQS queue + DLQ, deploys the Lambda zip, and wires the
   event-source mapping.
3. `worker-outbox` polls the outbox table and dispatches deliveries to
   SQS.
4. floci's event-source mapping invokes the Lambda with the SQS event.
5. The Lambda POSTs to the subscriber URL with the HMAC signature
   header.
6. `mock-receiver` (port 8888) captures the requests for inspection.

**Why a shell init script and not Terraform**: for a sample of this
size, ~50 lines of `awslocal` calls are more transparent than the
equivalent Terraform module that emits the same AWS API calls. In a
real deployment the same resources are declared as Terraform / CDK
targeting real AWS — the `awslocal` calls map one-to-one.

## What's intentionally missing for production

A non-exhaustive list of things a real deployment would add:

- **Lambda → backend feedback loop**: today the
  `webhook_deliveries.status` field captures only the dispatch event.
  A real system has the Lambda update the row on completion (Postgres
  access from the Lambda, or a "delivery completed" event back through
  SQS or EventBridge).
- **Admin replay endpoints**: `POST /api/admin/webhook-deliveries/
  {id}/replay` and `GET /api/admin/webhook-deliveries?status=failed`.
  Listed in the spec as "if time permits"; deferred.
- **Real subscriber configuration**: subscribers today come from a
  static `config/packages/subscribers.yaml`. Production would manage
  them via an admin UI / database, with per-subscriber rate limiting
  and back-off policies.
- **PHI / HIPAA**: the service is HIPAA-aware in shape (audit logging,
  encrypted-at-rest assumptions) but stores no real PHI. A production
  deployment would address encryption-in-transit between every hop,
  KMS key management, and access auditing.
- **Multi-region + failover**: single-region Postgres + Redis +
  floci/SQS for the sample.
- **Real Lambda monitoring**: CloudWatch metrics + alarms for invoke
  failures, throttles, and DLQ depth. None of this exists locally.
- **Real IaC**: Terraform/CDK pointed at AWS, with a CI pipeline that
  packages and deploys the Lambda independently of the backend.

## Trade-offs considered

- **`MerchantId` deliberately omitted**: Spec Section 4.2 lists
  `MerchantId` as an identifier value object, but no aggregate
  references it — Merchant is modelled as an identity-less value
  object on the Authorization aggregate. Adding an unused identifier
  class is exactly the cruft Section 17.1 warns against; the principle
  beat the literal listing.
- **JSONB for `Merchant` / MCC list**: alternatives were embeddables
  (more structurally correct, but Doctrine's nullable-embeddable
  story is awkward for the optional `GeoLocation`) or per-row tables
  (over-modeled for data that never gets queried independently).
- **async-aws/sqs vs aws/aws-sdk-php**: async-aws is ~8× smaller and
  more modern; we only use SQS so the slimmer SDK is the right call.
- **`Card::authorize()` does NOT raise events**: only the calling
  context (the command handler) does, by creating an `Authorization`
  aggregate that raises `CardAuthorizationApproved` / `Declined`. The
  Card stays focused on the decision; the cross-aggregate event
  belongs on the aggregate that represents the decision record.
- **Manual `++$this->version` removed from Card**: Doctrine's
  optimistic-lock handling increments the version field automatically
  on UPDATE; the manual bump conflicted with that and raised
  `OptimisticLockException` on every approved authorization flush.
