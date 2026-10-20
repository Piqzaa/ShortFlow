<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les favoris de modèles IA par propriétaire sans modifier les générations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD ai_favorites JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM app_user WHERE ai_favorites::text <> '[]'") > 0, 'Les favoris existants doivent être conservés.');
        $this->addSql('ALTER TABLE app_user DROP ai_favorites');
    }
}
