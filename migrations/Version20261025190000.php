<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil éditorial des séries et propositions locales isolées, sans modifier les brouillons ou les consentements existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_series ADD editorial_profile JSON DEFAULT '{}' NOT NULL");
        $this->addSql("ALTER TABLE publication_draft ADD editorial JSON DEFAULT '{}' NOT NULL");
        $this->addSql("ALTER TABLE story_proposal ADD kind VARCHAR(16) DEFAULT 'story' NOT NULL");
        $this->addSql('CREATE INDEX editorial_owner_scope ON story_proposal (owner_id, kind, scope, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM story_proposal WHERE kind <> 'story'") > 0, 'Préserver les propositions éditoriales.');
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM publication_draft WHERE editorial::text NOT IN ('{}', '[]')") > 0, 'Préserver les textes et leur origine.');
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_series WHERE editorial_profile::text NOT IN ('{}', '[]')") > 0, 'Préserver les profils éditoriaux.');
        $this->addSql('DROP INDEX editorial_owner_scope');
        $this->addSql('ALTER TABLE story_proposal DROP kind');
        $this->addSql('ALTER TABLE publication_draft DROP editorial');
        $this->addSql('ALTER TABLE ai_series DROP editorial_profile');
    }
}
