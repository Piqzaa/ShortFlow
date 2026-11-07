<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planned calendar for AI series, without publication activation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_series ADD calendar_plan JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_series WHERE calendar_plan::text NOT IN ('{}', '[]')") > 0, 'Preserve planned calendars before rollback.');
        $this->addSql('ALTER TABLE ai_series DROP calendar_plan');
    }
}
