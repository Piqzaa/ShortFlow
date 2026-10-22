<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

final class Version20261022030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les caractéristiques vidéo et la visibilité YouTube ; isole les tâches IA/statistiques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video_asset ADD technical_metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE youtube_publication ADD effective_privacy VARCHAR(10) DEFAULT NULL');
        $this->moveBackground('publications', 'background');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM video_asset WHERE technical_metadata IS NOT NULL') > 0 || (int) $this->connection->fetchOne('SELECT COUNT(*) FROM youtube_publication WHERE effective_privacy IS NOT NULL') > 0, 'Préserver les caractéristiques et les visibilités enregistrées.');
        $this->moveBackground('background', 'publications');
        $this->addSql('ALTER TABLE video_asset DROP technical_metadata');
        $this->addSql('ALTER TABLE youtube_publication DROP effective_privacy');
    }

    private function moveBackground(string $from, string $to): void
    {
        $serializer = new PhpSerializer();
        foreach ($this->connection->iterateAssociative('SELECT id, body, headers FROM messenger_messages WHERE queue_name = ? AND delivered_at IS NULL', [$from]) as $row) {
            $headers = json_decode($row['headers'], true);
            // Doctrine uses native PHP serialization by default, with empty headers.
            // Inspect the type without instantiating a message or invoking its handler.
            $type = $headers['type'] ?? $serializer->getMessageType(['body' => $row['body']]);
            if (in_array($type, ['App\\Message\\GenerateAiVideo', 'App\\Message\\RefreshPublicationStatistics'], true)) {
                $this->addSql('UPDATE messenger_messages SET queue_name = ? WHERE id = ? AND queue_name = ? AND delivered_at IS NULL', [$to, $row['id'], $from]);
            }
        }
    }
}
