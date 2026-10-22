<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enregistre les styles de texte personnels du mini éditeur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user ADD editor_styles JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM app_user WHERE editor_styles::text <> '[]'") > 0, 'Préserver les styles enregistrés.');
        $this->addSql('ALTER TABLE app_user DROP editor_styles');
    }
}
