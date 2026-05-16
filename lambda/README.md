# Outbound webhook Lambda

A small TypeScript handler that consumes SQS messages produced by the
backend's `app:outbox:publish` worker and POSTs each webhook to its
subscriber URL.

The handler is intentionally minimal: the backend pre-builds the URL,
payload, and HMAC signature header, so this function's only job is to
make the HTTP call and translate the result into SQS's partial-batch
response format.

## Build

```bash
cd lambda
npm install
npm run package    # → dist/handler.zip
```

The init script in `infra/init/setup.sh` runs `npm run package` and uploads
the resulting `dist/handler.zip` to floci's Lambda emulator on startup,
along with the SQS queue and event-source mapping.

## Why TypeScript / Node 22?

Node's cold-start is the smallest of the supported Lambda runtimes (well
under 200ms), the runtime has `fetch` built in, and the bundled handler
fits in a single ~5KB file. The trade-off is one more language in the
repo — see `ARCHITECTURE.md` for the reasoning.
