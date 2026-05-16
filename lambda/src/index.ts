import type { SQSBatchItemFailure, SQSBatchResponse, SQSEvent } from "aws-lambda";

/**
 * The SQS message body shape the outbox worker dispatches.
 * Keep in sync with App\Infrastructure\Webhook\SqsOutboundWebhookDispatcher.
 */
interface DeliveryMessage {
  delivery_id: string;
  subscriber_id: string;
  event_id: string;
  event_type: string;
  url: string;
  /** Already-serialized JSON body to POST verbatim. */
  payload: string;
  /** "t=<unix>,v1=<hmac>" — opaque to this handler. */
  signature_header: string;
}

/**
 * Outbound webhook delivery Lambda. Triggered by SQS event-source mapping;
 * one invocation may carry multiple records. Failures are reported via
 * `batchItemFailures` so SQS re-queues only the messages that actually
 * failed (partial-batch responses).
 */
export async function handler(event: SQSEvent): Promise<SQSBatchResponse> {
  const failures: SQSBatchItemFailure[] = [];

  for (const record of event.Records) {
    let message: DeliveryMessage;
    try {
      message = JSON.parse(record.body) as DeliveryMessage;
    } catch (parseError) {
      // Malformed message bodies are unrecoverable — they cannot succeed
      // on retry, so let the message exhaust its receive count and go
      // to the dead-letter queue.
      console.error("delivery_message_parse_failed", {
        messageId: record.messageId,
        error: errorString(parseError),
      });
      failures.push({ itemIdentifier: record.messageId });
      continue;
    }

    try {
      const response = await fetch(message.url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Webhook-Signature": message.signature_header,
          "X-Webhook-Event-Id": message.event_id,
          "X-Webhook-Delivery-Id": message.delivery_id,
          "X-Webhook-Event-Type": message.event_type,
        },
        body: message.payload,
        // Aggressive deadline keeps a stuck subscriber from holding the
        // whole batch hostage. SQS retry handles temporary failures.
        signal: AbortSignal.timeout(10_000),
      });

      if (!response.ok) {
        console.error("delivery_non_2xx", {
          deliveryId: message.delivery_id,
          subscriberId: message.subscriber_id,
          status: response.status,
        });
        failures.push({ itemIdentifier: record.messageId });
        continue;
      }

      console.log("delivery_ok", {
        deliveryId: message.delivery_id,
        subscriberId: message.subscriber_id,
        eventType: message.event_type,
      });
    } catch (httpError) {
      console.error("delivery_http_failed", {
        deliveryId: message.delivery_id,
        error: errorString(httpError),
      });
      failures.push({ itemIdentifier: record.messageId });
    }
  }

  return { batchItemFailures: failures };
}

function errorString(error: unknown): string {
  if (error instanceof Error) {
    return `${error.name}: ${error.message}`;
  }
  return String(error);
}
