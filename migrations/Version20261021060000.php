<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les fragments audio indépendants du montage, avec reprise de la musique existante.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_edit ADD audio_clips JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM video_edit WHERE audio_clips IS NOT NULL') > 0, 'Conserver les fragments audio existants.');
        $this->addSql('ALTER TABLE video_edit DROP audio_clips');
    }
}
