<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link publication preparations to their source episode without changing existing drafts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication_draft ADD episode_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication_draft ADD CONSTRAINT FK_DRAFT_EPISODE FOREIGN KEY (episode_id) REFERENCES ai_episode (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DRAFT_EPISODE ON publication_draft (episode_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication_draft DROP CONSTRAINT FK_DRAFT_EPISODE');
        $this->addSql('DROP INDEX IDX_DRAFT_EPISODE');
        $this->addSql('ALTER TABLE publication_draft DROP episode_id');
    }
}
