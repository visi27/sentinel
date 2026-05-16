<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Application\Outbox\OutboxReader;
use App\Application\Shared\Clock;
use App\Application\Shared\TransactionManager;
use App\Application\Webhook\OutboundWebhookDispatcher;
use App\Application\Webhook\SubscriberRegistry;
use App\Application\Webhook\WebhookDelivery;
use App\Application\Webhook\WebhookDeliveryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Polls the outbox, fans events out to matching subscribers, hands each
 * delivery to the SQS dispatcher, and marks the outbox row published.
 *
 * Run continuously in production (--loop) and one-shot in CI/integration
 * tests (--once).
 */
#[AsCommand(
    name: 'app:outbox:publish',
    description: 'Drain unpublished outbox events to subscriber webhooks via SQS',
)]
final class OutboxPublishCommand extends Command
{
    public function __construct(
        private readonly OutboxReader $outbox,
        private readonly SubscriberRegistry $subscribers,
        private readonly OutboundWebhookDispatcher $dispatcher,
        private readonly WebhookDeliveryRepository $deliveryRepository,
        private readonly TransactionManager $transactionManager,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('once', null, InputOption::VALUE_NONE, 'Process a single batch and exit.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Events per batch.', '100')
            ->addOption('idle-seconds', null, InputOption::VALUE_REQUIRED, 'Sleep when the queue is empty.', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $once = (bool) $input->getOption('once');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $idleSeconds = max(0, (int) $input->getOption('idle-seconds'));

        $shouldStop = false;
        if (function_exists('pcntl_signal')) {
            pcntl_signal(\SIGTERM, static function () use (&$shouldStop): void {
                $shouldStop = true;
            });
            pcntl_signal(\SIGINT, static function () use (&$shouldStop): void {
                $shouldStop = true;
            });
        }

        do {
            $processed = $this->processBatch($batchSize, $output);
            if ($once) {
                break;
            }
            if (0 === $processed && $idleSeconds > 0) {
                sleep($idleSeconds);
            }
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        } while (!$shouldStop);

        return Command::SUCCESS;
    }

    private function processBatch(int $batchSize, OutputInterface $output): int
    {
        $processedCount = 0;

        // Batch and outbox marks share a transaction so a crash mid-batch
        // leaves the outbox accurate: rows that weren't yet dispatched stay
        // unpublished and the next worker run picks them up.
        $this->transactionManager->run(function () use ($batchSize, $output, &$processedCount): void {
            foreach ($this->outbox->fetchUnpublishedBatch($batchSize) as $record) {
                try {
                    $subscribers = $this->subscribers->listenersFor($record->eventType);
                    foreach ($subscribers as $subscriber) {
                        $delivery = WebhookDelivery::forEvent($subscriber, $record, $this->clock->now());
                        $this->deliveryRepository->recordDispatch($delivery);
                        $this->dispatcher->dispatch($delivery);
                    }
                    $this->outbox->markPublished($record->eventId);
                    $output->writeln(sprintf(
                        '<info>published %s → %d subscriber(s)</info>',
                        $record->eventType,
                        count($subscribers),
                    ));
                } catch (\Throwable $e) {
                    $this->outbox->markFailed($record->eventId, $e->getMessage());
                    $output->writeln(sprintf(
                        '<error>failed to publish %s: %s</error>',
                        $record->eventId,
                        $e->getMessage(),
                    ));
                }
                ++$processedCount;
            }
        });

        return $processedCount;
    }
}
