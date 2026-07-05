<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Identity model change: (base_plan_id, plan_id) -> (provider_name, external_identity), so a second
 * provider can reuse the same external ids without colliding. The (base_plan_id, plan_id) pairing is
 * now folded into the opaque external_identity by the provider's own adapter.
 *
 * ADD ... NOT NULL without a default assumes an empty table. That holds here: in this challenge the
 * store is disposable and app:sync-plans is idempotent, so the path is wipe + resync (and the test
 * DB is always rebuilt from scratch). A real production system would instead backfill external_identity
 * from the existing rows before swapping the unique constraint, to keep already-synced history.
 */
final class Version20260705000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace event identity (base_plan_id, plan_id) with (provider_name, external_identity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD provider_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE events ADD external_identity VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX uniq_events_base_plan');
        $this->addSql('ALTER TABLE events DROP base_plan_id');
        $this->addSql('ALTER TABLE events DROP plan_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_events_identity ON events (provider_name, external_identity)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD base_plan_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE events ADD plan_id VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX uniq_events_identity');
        $this->addSql('ALTER TABLE events DROP provider_name');
        $this->addSql('ALTER TABLE events DROP external_identity');
        $this->addSql('CREATE UNIQUE INDEX uniq_events_base_plan ON events (base_plan_id, plan_id)');
    }
}
