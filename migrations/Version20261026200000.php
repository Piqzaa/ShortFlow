<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver plusieurs comptes par réseau et figer les destinations des occurrences sans supprimer les connexions existantes.';
    }

    public function up(Schema $schema): void
    {
        foreach ([['youtube', '5BA16D24', 'channel_id'], ['instagram', '127AD790', 'instagram_id'], ['tiktok', '418B184', 'open_id']] as [$network, $hash, $field]) {
            $this->addSql('DROP INDEX UNIQ_'.$hash.'7E3C61F9');
            $this->addSql('CREATE INDEX IDX_'.$hash.'7E3C61F9 ON '.$network.'_connection (owner_id)');
            $this->addSql('CREATE UNIQUE INDEX '.$network.'_owner_identity ON '.$network.'_connection (owner_id, '.$field.')');
        }
        $this->addSql("ALTER TABLE series_occurrence ADD accounts JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT owner_id FROM '.$network.'_connection GROUP BY owner_id HAVING COUNT(*) > 1) accounts') > 0, 'Préserver les comptes multiples.');
        }
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM series_occurrence WHERE accounts::text NOT IN ('{}', '[]')") > 0, 'Préserver les destinations des épisodes.');
        $this->addSql('ALTER TABLE series_occurrence DROP accounts');
        foreach ([['youtube', '5BA16D24'], ['instagram', '127AD790'], ['tiktok', '418B184']] as [$network, $hash]) {
            $this->addSql('DROP INDEX '.$network.'_owner_identity');
            $this->addSql('DROP INDEX IDX_'.$hash.'7E3C61F9');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_'.$hash.'7E3C61F9 ON '.$network.'_connection (owner_id)');
        }
    }
}
