<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve la référence privée facultative des générations image vers vidéo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_generation ADD reference_image VARCHAR(52) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM ai_generation WHERE reference_image IS NOT NULL') > 0, 'Les images de référence existantes doivent être conservées.');
        $this->addSql('ALTER TABLE ai_generation DROP reference_image');
    }
}
