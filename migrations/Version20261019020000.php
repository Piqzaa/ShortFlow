<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clés BYOK par fournisseur ; les clés existantes restent associées à Fal.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_credential ADD provider VARCHAR(16) DEFAULT 'fal' NOT NULL");
        $this->addSql('DROP INDEX UNIQ_64594F1F7E3C61F9');
        $this->addSql('CREATE INDEX IDX_64594F1F7E3C61F9 ON ai_credential (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_owner_provider ON ai_credential (owner_id, provider)');
        $this->addSql('ALTER TABLE ai_credential ALTER provider DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_credential WHERE provider <> 'fal'") > 0, 'Retirer explicitement les autres clés avant le retour arrière.');
        $this->addSql('DROP INDEX uniq_ai_owner_provider');
        $this->addSql('DROP INDEX IDX_64594F1F7E3C61F9');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64594F1F7E3C61F9 ON ai_credential (owner_id)');
        $this->addSql('ALTER TABLE ai_credential DROP provider');
    }
}
