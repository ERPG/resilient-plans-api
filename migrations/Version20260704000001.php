<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: plans table (stub — will be replaced with real domain model)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plans (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE plans');
    }
}
