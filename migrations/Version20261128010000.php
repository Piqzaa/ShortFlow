<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128010000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_asset ADD assembly_episode_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_asset ADD CONSTRAINT FK_8DCD53FEF170D8D8 FOREIGN KEY (assembly_episode_id) REFERENCES ai_episode (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8DCD53FEF170D8D8 ON video_asset (assembly_episode_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_asset DROP assembly_episode_id');
    }
}
