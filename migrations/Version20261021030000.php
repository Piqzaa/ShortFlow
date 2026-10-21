<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve la préparation à laquelle un montage pourra être appliqué.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_edit ADD publication_context JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM video_edit WHERE publication_context IS NOT NULL') > 0, 'Conserver les retours de montage existants.');
        $this->addSql('ALTER TABLE video_edit DROP publication_context');
    }
}
