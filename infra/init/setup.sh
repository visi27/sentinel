#!/usr/bin/env sh
# floci/localstack ready-state init: creates the SQS queue, DLQ, Lambda
# function, and event-source mapping required for the outbound webhook
# pipeline.
#
# This script is mounted into /etc/localstack/init/ready.d/ and runs
# automatically once floci's services are healthy.

set -e

ACCOUNT=000000000000
REGION=${AWS_DEFAULT_REGION:-us-east-1}
QUEUE=sentinel-outbound-deliveries
DLQ=${QUEUE}-dlq
FUNCTION=sentinel-outbound-webhook
ROLE_ARN=arn:aws:iam::${ACCOUNT}:role/lambda-role

echo "[init] creating dead-letter queue ${DLQ}"
awslocal sqs create-queue --queue-name "${DLQ}" >/dev/null
DLQ_ARN=$(awslocal sqs get-queue-attributes \
    --queue-url "http://localhost:4566/${ACCOUNT}/${DLQ}" \
    --attribute-names QueueArn \
    --query 'Attributes.QueueArn' \
    --output text)

echo "[init] creating main queue ${QUEUE} with redrive policy"
awslocal sqs create-queue --queue-name "${QUEUE}" --attributes "{\
  \"VisibilityTimeout\":\"60\",\
  \"RedrivePolicy\":\"{\\\"deadLetterTargetArn\\\":\\\"${DLQ_ARN}\\\",\\\"maxReceiveCount\\\":\\\"5\\\"}\"\
}" >/dev/null

QUEUE_ARN=$(awslocal sqs get-queue-attributes \
    --queue-url "http://localhost:4566/${ACCOUNT}/${QUEUE}" \
    --attribute-names QueueArn \
    --query 'Attributes.QueueArn' \
    --output text)

if [ ! -f /opt/lambda/handler.zip ]; then
    echo "[init] /opt/lambda/handler.zip is missing — run lambda-builder first"
    exit 1
fi

echo "[init] creating Lambda function ${FUNCTION}"
awslocal lambda create-function \
    --function-name "${FUNCTION}" \
    --runtime nodejs22.x \
    --role "${ROLE_ARN}" \
    --handler index.handler \
    --zip-file fileb:///opt/lambda/handler.zip \
    --timeout 30 \
    --memory-size 256 >/dev/null

# Wait for the Lambda to reach the Active state before mapping it to SQS.
echo "[init] waiting for Lambda to become active"
awslocal lambda wait function-active-v2 --function-name "${FUNCTION}"

echo "[init] mapping SQS → Lambda"
awslocal lambda create-event-source-mapping \
    --function-name "${FUNCTION}" \
    --event-source-arn "${QUEUE_ARN}" \
    --batch-size 10 \
    --function-response-types ReportBatchItemFailures >/dev/null

echo "[init] outbound webhook pipeline ready"
