<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une piste musicale privée facultative aux montages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_edit ADD music_name VARCHAR(52) DEFAULT NULL, ADD music_title VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM video_edit WHERE music_name IS NOT NULL') > 0, 'Conserver les pistes musicales existantes.');
        $this->addSql('ALTER TABLE video_edit DROP music_name, DROP music_title');
    }
}
