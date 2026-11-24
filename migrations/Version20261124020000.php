<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124020000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD paid_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT 1 FROM app_user WHERE paid_until IS NOT NULL LIMIT 1'), 'Preserve existing account entitlements before rollback.');
        $this->addSql('ALTER TABLE app_user DROP paid_until');
    }
}
