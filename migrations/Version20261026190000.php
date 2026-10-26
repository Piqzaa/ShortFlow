<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clé facultative de préparation par vidéo pour dédupliquer les sélections, sans modifier les anciens brouillons.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication_draft ADD preparation_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX publication_preparation_key ON publication_draft (preparation_key)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM publication_draft WHERE preparation_key IS NOT NULL') > 0, 'Préserver les préparations dédupliquées.');
        $this->addSql('DROP INDEX publication_preparation_key');
        $this->addSql('ALTER TABLE publication_draft DROP preparation_key');
    }
}
