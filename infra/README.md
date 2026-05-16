# Infrastructure

Local AWS emulation via [floci](https://github.com/floci-io/floci) (a
LocalStack-compatible drop-in). The `init/setup.sh` script runs inside
the floci container as soon as its services report healthy, and creates:

- An SQS queue, `sentinel-outbound-deliveries`, with a dead-letter queue
  (`sentinel-outbound-deliveries-dlq`) attached via redrive policy
  (maxReceiveCount=5).
- A Node.js 22 Lambda function, `sentinel-outbound-webhook`, deployed
  from the zip the `lambda-builder` compose service produces.
- An event-source mapping that connects the queue to the Lambda with
  partial-batch failure reporting enabled.

## Why a shell init script and not Terraform?

For a sample of this size, a 50-line bash script is more transparent
than a Terraform module that boils down to the same `aws` CLI calls.
The script is self-contained, runs deterministically, and survives an
audit by anyone familiar with the AWS CLI. In a production deployment
the same resources would be expressed as Terraform/CDK targeting real
AWS — the `awslocal` calls map one-to-one to that.

## Running locally

```bash
make up
```

Triggers the chain: `lambda-builder` (one-shot) → `floci` (health-checked,
runs `init/setup.sh` on ready) → `worker-outbox` (long-running publisher)
→ `app` (HTTP API). Once the stack is up, an inbound authorization through
the webhook controller will produce an outbox event, the worker dispatches
it to SQS, floci's event-source mapping invokes the Lambda, and the
delivery lands at the `mock-receiver` service. Visit
[http://localhost:8888](http://localhost:8888) to inspect the captured
request.
