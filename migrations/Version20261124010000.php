<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve small private traces after a published temporary video expires.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE published_video_trace (id INT NOT NULL, owner_id INT NOT NULL, title VARCHAR(120) NOT NULL, poster TEXT DEFAULT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_published_video_trace_owner ON published_video_trace (owner_id, removed_at, id)');
        $this->addSql('ALTER TABLE published_video_trace ADD CONSTRAINT fk_published_video_trace_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM published_video_trace') > 0, 'Conserver les traces des vidéos publiées.');
        $this->addSql('DROP TABLE published_video_trace');
    }
}
