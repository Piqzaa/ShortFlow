<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Series publication defaults, stable imported episodes and durable publication source links.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_series ADD publication_settings JSON DEFAULT '{}' NOT NULL");
        $this->addSql("ALTER TABLE video_series ADD publication_settings JSON DEFAULT '{}' NOT NULL");
        $this->addSql('ALTER TABLE video_series_episode ADD active BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE publication_draft ADD video_episode_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_draft_video_episode ON publication_draft (video_episode_id)');
        $this->addSql('ALTER TABLE publication_draft ADD CONSTRAINT FK_DRAFT_VIDEO_EPISODE FOREIGN KEY (video_episode_id) REFERENCES video_series_episode (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM publication_draft WHERE video_episode_id IS NOT NULL') > 0, 'Preserve series publication links before rollback.');
        $this->abortIf((int) $this->connection->fetchOne("SELECT (SELECT COUNT(*) FROM ai_series WHERE publication_settings::text <> '{}') + (SELECT COUNT(*) FROM video_series WHERE publication_settings::text <> '{}') + (SELECT COUNT(*) FROM video_series_episode WHERE active = false)") > 0, 'Preserve series settings and archived episodes before rollback.');
        $this->addSql('ALTER TABLE publication_draft DROP CONSTRAINT FK_DRAFT_VIDEO_EPISODE');
        $this->addSql('DROP INDEX idx_draft_video_episode');
        $this->addSql('ALTER TABLE publication_draft DROP video_episode_id');
        $this->addSql('ALTER TABLE video_series_episode DROP active');
        $this->addSql('ALTER TABLE video_series DROP publication_settings');
        $this->addSql('ALTER TABLE ai_series DROP publication_settings');
    }
}
