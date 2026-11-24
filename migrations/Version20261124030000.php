<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124030000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD stripe_customer_id VARCHAR(64) DEFAULT NULL, ADD stripe_subscription_status VARCHAR(24) DEFAULT NULL, ADD stripe_cancellation_scheduled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_stripe_customer ON app_user (stripe_customer_id)');
        $this->addSql('CREATE TABLE stripe_billing_event (id VARCHAR(96) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT 1 FROM app_user WHERE stripe_customer_id IS NOT NULL LIMIT 1'), 'Preserve Stripe customer links before rollback.');
        $this->addSql('DROP TABLE stripe_billing_event');
        $this->addSql('DROP INDEX uniq_app_user_stripe_customer');
        $this->addSql('ALTER TABLE app_user DROP stripe_customer_id, DROP stripe_subscription_status, DROP stripe_cancellation_scheduled');
    }
}
