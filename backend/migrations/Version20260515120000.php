<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: cards, authorizations, outbox_events.
 *
 * Tables are intentionally written by hand rather than generated from the
 * Doctrine mappings so the index strategy (unique processor_auth_id,
 * partial index on unpublished outbox rows, etc.) lives in the migration —
 * the source of truth for production-relevant DDL.
 */
final class Version20260515120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: cards, authorizations, outbox_events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cards (
                id UUID NOT NULL PRIMARY KEY,
                cardholder_id UUID NOT NULL,
                status VARCHAR(20) NOT NULL,
                spending_limits_per_transaction_minor_units BIGINT NOT NULL,
                spending_limits_per_transaction_currency VARCHAR(3) NOT NULL,
                spending_limits_daily_minor_units BIGINT NOT NULL,
                spending_limits_daily_currency VARCHAR(3) NOT NULL,
                spending_limits_monthly_minor_units BIGINT NOT NULL,
                spending_limits_monthly_currency VARCHAR(3) NOT NULL,
                available_balance_minor_units BIGINT NOT NULL,
                available_balance_currency VARCHAR(3) NOT NULL,
                daily_spend_minor_units BIGINT NOT NULL,
                daily_spend_currency VARCHAR(3) NOT NULL,
                monthly_spend_minor_units BIGINT NOT NULL,
                monthly_spend_currency VARCHAR(3) NOT NULL,
                allowed_merchant_category_codes JSONB NOT NULL,
                issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                activated_at TIMESTAMP(0) WITH TIME ZONE NULL,
                closed_at TIMESTAMP(0) WITH TIME ZONE NULL,
                last_spend_date TIMESTAMP(0) WITH TIME ZONE NULL,
                version INTEGER NOT NULL DEFAULT 1
            )
        SQL);
        $this->addSql('CREATE INDEX idx_cards_cardholder_id ON cards (cardholder_id)');
        $this->addSql('CREATE INDEX idx_cards_status ON cards (status)');

        $this->addSql(<<<'SQL'
            CREATE TABLE authorizations (
                id UUID NOT NULL PRIMARY KEY,
                card_id UUID NOT NULL,
                processor_auth_id VARCHAR(255) NOT NULL,
                amount_minor_units BIGINT NOT NULL,
                amount_currency VARCHAR(3) NOT NULL,
                merchant JSONB NOT NULL,
                status VARCHAR(20) NOT NULL,
                decline_reason VARCHAR(50) NULL,
                requested_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                decided_at TIMESTAMP(0) WITH TIME ZONE NULL,
                reversed_at TIMESTAMP(0) WITH TIME ZONE NULL
            )
        SQL);
        // Hard idempotency backstop: the application checks first via Redis,
        // but this index is the durable guarantee under a race.
        $this->addSql('CREATE UNIQUE INDEX uniq_authorizations_processor_auth_id ON authorizations (processor_auth_id)');
        $this->addSql('CREATE INDEX idx_authorizations_card_id ON authorizations (card_id)');
        $this->addSql('CREATE INDEX idx_authorizations_requested_at ON authorizations (requested_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE outbox_events (
                id UUID NOT NULL PRIMARY KEY,
                event_type VARCHAR(255) NOT NULL,
                aggregate_id VARCHAR(255) NOT NULL,
                aggregate_type VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL,
                occurred_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                published_at TIMESTAMP(6) WITH TIME ZONE NULL,
                publish_attempts INTEGER NOT NULL DEFAULT 0,
                last_attempt_at TIMESTAMP(6) WITH TIME ZONE NULL,
                last_error TEXT NULL
            )
        SQL);
        // Partial index keeps the outbox-worker query fast even after years
        // of published events accumulate.
        $this->addSql('CREATE INDEX idx_outbox_unpublished ON outbox_events (occurred_at) WHERE published_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS outbox_events');
        $this->addSql('DROP TABLE IF EXISTS authorizations');
        $this->addSql('DROP TABLE IF EXISTS cards');
    }
}
