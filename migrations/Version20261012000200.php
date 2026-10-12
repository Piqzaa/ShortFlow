<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligner le nom de l’index unique sur la convention Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_waitlist_email RENAME TO UNIQ_67447574E7927C74');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX UNIQ_67447574E7927C74 RENAME TO uniq_waitlist_email');
    }
}
