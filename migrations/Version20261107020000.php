<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Writing style for imported video series.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE video_series ADD editorial_profile JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM video_series WHERE editorial_profile::text <> '{}'") > 0, 'Preserve writing styles before rollback.');
        $this->addSql('ALTER TABLE video_series DROP editorial_profile');
    }
}
