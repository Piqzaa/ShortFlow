<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve l’image de fin et le cadrage choisi sans modifier les générations existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_generation ADD end_reference_image VARCHAR(52) DEFAULT NULL');
        $this->addSql("ALTER TABLE ai_generation ADD image_fit VARCHAR(8) DEFAULT 'crop' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM ai_generation WHERE end_reference_image IS NOT NULL OR image_fit <> 'crop'") > 0, 'Les images de fin et cadrages existants doivent être conservés.');
        $this->addSql('ALTER TABLE ai_generation DROP end_reference_image, DROP image_fit');
    }
}
