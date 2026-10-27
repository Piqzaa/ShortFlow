<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Séparer attente et exécution des propositions locales, sans modifier les préparations existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE story_proposal ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE story_proposal ADD phase_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX story_pending_age ON story_proposal (status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM story_proposal WHERE status IN ('story', 'shots', 'editorial')") > 0, 'Préserver les délais des rédactions en cours.');
        $this->addSql('DROP INDEX story_pending_age');
        $this->addSql('ALTER TABLE story_proposal DROP started_at');
        $this->addSql('ALTER TABLE story_proposal DROP phase_started_at');
    }
}
