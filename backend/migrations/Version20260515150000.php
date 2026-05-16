<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add webhook_deliveries tracking table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE webhook_deliveries (
                id UUID NOT NULL PRIMARY KEY,
                subscriber_id VARCHAR(255) NOT NULL,
                event_id UUID NOT NULL,
                event_type VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                status VARCHAR(20) NOT NULL,
                attempt_count INTEGER NOT NULL DEFAULT 0,
                last_attempt_at TIMESTAMP(6) WITH TIME ZONE NULL,
                last_error TEXT NULL,
                delivered_at TIMESTAMP(6) WITH TIME ZONE NULL,
                created_at TIMESTAMP(6) WITH TIME ZONE NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql('CREATE INDEX idx_webhook_deliveries_status ON webhook_deliveries (status, created_at)');
        $this->addSql('CREATE INDEX idx_webhook_deliveries_event_id ON webhook_deliveries (event_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS webhook_deliveries');
    }
}
