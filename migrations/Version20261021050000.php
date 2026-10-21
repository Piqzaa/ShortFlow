<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les textes libres positionnables aux montages existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE video_edit ADD texts JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE video_edit ALTER COLUMN texts DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM video_edit WHERE texts::text <> '[]'") > 0, 'Conserver les textes des montages existants.');
        $this->addSql('ALTER TABLE video_edit DROP texts');
    }
}
