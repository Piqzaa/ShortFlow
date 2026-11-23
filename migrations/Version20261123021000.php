<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123021000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record Instagram and TikTok publication completion times for video retention.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE instagram_publication ADD published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE tiktok_publication ADD published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE instagram_publication DROP published_at');
        $this->addSql('ALTER TABLE tiktok_publication DROP published_at');
    }
}
