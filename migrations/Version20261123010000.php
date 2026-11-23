<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow one publication per source and YouTube channel in a submission.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_youtube_submission_video_channel ON youtube_publication (submission_key, source_id, channel_id)');
        $this->addSql('DROP INDEX uniq_youtube_submission_video');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT 1 FROM youtube_publication WHERE source_id IS NOT NULL GROUP BY submission_key, source_id HAVING COUNT(*) > 1 LIMIT 1'), 'Preserve submissions to multiple YouTube channels before rollback.');
        $this->addSql('CREATE UNIQUE INDEX uniq_youtube_submission_video ON youtube_publication (submission_key, source_id)');
        $this->addSql('DROP INDEX uniq_youtube_submission_video_channel');
    }
}
