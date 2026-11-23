<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep existing videos permanently and track the expiry of new temporary imports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_asset ADD temporary BOOLEAN DEFAULT FALSE NOT NULL, ADD retention_due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_video_asset_temporary ON video_asset (temporary, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_video_asset_temporary');
        $this->addSql('ALTER TABLE video_asset DROP temporary, DROP retention_due_at');
    }
}
