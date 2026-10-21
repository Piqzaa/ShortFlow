<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les réglages de cadrage/habillage et les sous-titres privés des montages existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE video_edit ADD settings JSON DEFAULT '{"fit":"contain","captionStyle":"off","intro":false}' NOT NULL
SQL);
        $this->addSql("ALTER TABLE video_edit ADD captions JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE video_edit ALTER settings DROP DEFAULT, ALTER captions DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne(<<<'SQL'
SELECT COUNT(*) FROM video_edit WHERE captions::jsonb <> '[]'::jsonb OR settings::jsonb <> '{"fit":"contain","captionStyle":"off","intro":false}'::jsonb
SQL) > 0, 'Les sous-titres et réglages existants doivent être conservés.');
        $this->addSql('ALTER TABLE video_edit DROP settings, DROP captions');
    }
}
