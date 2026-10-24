<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024180500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les cadres propres aux plans et les références de sujets des générations, sans modifier les anciennes préparations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_shot ADD inputs JSON DEFAULT '{}' NOT NULL");
        $this->addSql("ALTER TABLE ai_generation ADD input_settings JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_shot WHERE inputs::text NOT IN ('{}', '[]')") > 0, 'Préserver les références par plan.');
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_generation WHERE input_settings::text NOT IN ('{}', '[]')") > 0, 'Préserver les paramètres des générations.');
        $this->addSql('ALTER TABLE ai_shot DROP inputs');
        $this->addSql('ALTER TABLE ai_generation DROP input_settings');
    }
}
