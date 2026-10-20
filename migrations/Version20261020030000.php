<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve uniquement la dernière étape et la date de suivi fournisseur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_generation ADD provider_stage VARCHAR(12) DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_generation ADD provider_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM ai_generation WHERE provider_stage IS NOT NULL') > 0, 'Les observations existantes doivent être conservées.');
        $this->addSql('ALTER TABLE ai_generation DROP provider_stage, DROP provider_checked_at');
    }
}
