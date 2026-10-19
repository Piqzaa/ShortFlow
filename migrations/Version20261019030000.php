<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve le mode API des générations existantes et permet les nouveaux accès MCP explicites.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_generation ADD access_mode VARCHAR(8) DEFAULT 'api' NOT NULL");
        $this->addSql('ALTER TABLE ai_generation ADD access_account VARCHAR(128) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_generation WHERE access_mode <> 'api'") > 0, 'Les générations MCP doivent conserver leur mode d’accès.');
        $this->addSql('ALTER TABLE ai_generation DROP access_mode');
        $this->addSql('ALTER TABLE ai_generation DROP access_account');
    }
}
