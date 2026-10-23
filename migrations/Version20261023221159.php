<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023221159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les demandes de rédaction locale pour les traiter en arrière-plan.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE story_proposal (id VARCHAR(32) NOT NULL, scope INT NOT NULL, fingerprint TEXT NOT NULL, input JSON NOT NULL, result JSON DEFAULT NULL, status VARCHAR(16) NOT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX story_owner_scope ON story_proposal (owner_id, scope, created_at)');
        $this->addSql('CREATE INDEX IDX_BC008AEE7E3C61F9 ON story_proposal (owner_id)');
        $this->addSql('ALTER TABLE story_proposal ADD CONSTRAINT FK_BC008AEE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE story_proposal DROP CONSTRAINT FK_BC008AEE7E3C61F9');
        $this->addSql('DROP TABLE story_proposal');
    }
}
