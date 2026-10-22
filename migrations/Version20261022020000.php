<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute plusieurs fichiers musicaux privés par montage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_edit ADD music_files JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM video_edit WHERE music_files IS NOT NULL') > 0, 'Préserver les musiques.');
        $this->addSql('ALTER TABLE video_edit DROP music_files');
    }
}
