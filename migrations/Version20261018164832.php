<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261018164832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil privé : nom affiché, fuseau par défaut et photo facultative, sans suppression de données.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD display_name VARCHAR(80) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD timezone VARCHAR(64) DEFAULT \'Europe/Paris\' NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD avatar_name VARCHAR(52) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP display_name');
        $this->addSql('ALTER TABLE app_user DROP timezone');
        $this->addSql('ALTER TABLE app_user DROP avatar_name');
    }
}
