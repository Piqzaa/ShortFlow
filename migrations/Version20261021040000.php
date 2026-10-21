<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Protège les sauvegardes de montage concurrentes par révision.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_edit ADD revision INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_edit DROP revision');
    }
}
