<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: the events table. Rewrites a throwaway stub (never deployed) — acceptable only
 * because of that; from here on, schema changes are new migrations, never edits to this one.
 */
final class Version20260704000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: events table (uuid PK, business identity, prices, last_seen_at)';
    }

    public function up(Schema $schema): void
    {
        // TIMESTAMP WITHOUT TIME ZONE keeps the provider's wall-clock as-is; timestamptz would shift it.
        $this->addSql(<<<'SQL'
            CREATE TABLE events (
                id UUID NOT NULL,
                base_plan_id VARCHAR(255) NOT NULL,
                plan_id VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                min_price NUMERIC(10, 2) NOT NULL,
                max_price NUMERIC(10, 2) NOT NULL,
                last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_events_base_plan ON events (base_plan_id, plan_id)');
        $this->addSql('CREATE INDEX idx_events_dates ON events (start_date, end_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE events');
    }
}
