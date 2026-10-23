<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023195047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permet les vidéos à plans du studio et conserve le son par défaut pour les nouvelles préparations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_episode ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_episode ALTER series_id DROP NOT NULL');
        $this->addSql('ALTER TABLE ai_episode ALTER mute_plans SET DEFAULT false');
        $this->addSql('ALTER TABLE ai_episode ADD CONSTRAINT FK_E1AF12097E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E1AF12097E3C61F9 ON ai_episode (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM ai_episode WHERE series_id IS NULL') > 0, 'Préserver les vidéos à plans créées dans le studio.');
        $this->addSql('ALTER TABLE ai_episode DROP CONSTRAINT FK_E1AF12097E3C61F9');
        $this->addSql('DROP INDEX IDX_E1AF12097E3C61F9');
        $this->addSql('ALTER TABLE ai_episode DROP owner_id');
        $this->addSql('ALTER TABLE ai_episode ALTER mute_plans SET DEFAULT true');
        $this->addSql('ALTER TABLE ai_episode ALTER series_id SET NOT NULL');
    }
}
