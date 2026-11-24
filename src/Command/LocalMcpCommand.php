<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\McpCatalog;
use App\Service\PublicationException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Server;
use Mcp\Server\Transport\StdioTransport;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:mcp:local', description: 'Démarre le prototype MCP sur stdin/stdout pour un compte local confirmé.')]
final class LocalMcpCommand
{
    public function __construct(
        #[Autowire('%kernel.environment%')] private string $environment,
        private UserRepository $users,
        private McpCatalog $catalog,
    ) {
    }

    public function __invoke(#[Argument(description: 'Adresse du compte de test local.')] string $email): int
    {
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            fwrite(STDERR, "Prototype MCP réservé aux environnements dev et test.\n");

            return 1;
        }
        $owner = $this->users->findByEmail($email);
        if (!$owner || !$owner->isVerified()) {
            fwrite(STDERR, "Compte de test introuvable ou non confirmé.\n");

            return 1;
        }

        $catalog = $this->catalog;

        return Server::builder()
            ->setServerInfo('VydFlow local', '0.1.0')
            ->addTool(static fn (string $search = '', int $page = 1): array => $catalog->videos($owner, $search, $page), 'list_videos', description: 'Trouve les vidéos privées du compte local par titre.')
            ->addTool(static fn (): array => $catalog->accounts($owner), 'list_publication_accounts', description: 'Liste les comptes sociaux connectés au compte VydFlow.')
            ->addTool(static function (int $videoId, ?string $date = null) use ($catalog, $owner): array|CallToolResult {
                try {
                    return $catalog->preparation($owner, $videoId, $date);
                } catch (\InvalidArgumentException $error) {
                    return CallToolResult::error([new TextContent($error->getMessage())]);
                }
            }, 'open_publication_preparation', description: 'Renvoie le lien privé de préparation pour cette vidéo. La date facultative suit le format AAAA-MM-JJ.')
            ->addTool(static function (int $videoId, string $network, int $accountId, string $title, string $text, string $hashtags = '') use ($catalog, $owner): array|CallToolResult {
                try {
                    return $catalog->draft($owner, $videoId, $network, $accountId, $title, $text, $hashtags);
                } catch (PublicationException $error) {
                    return CallToolResult::error([new TextContent($error->getMessage())]);
                }
            }, 'create_publication_draft', description: 'Enregistre un brouillon privé pour une vidéo et un compte connecté. Les hashtags sont une chaîne séparée par des espaces. La confirmation se fait dans VydFlow.')
            ->build()
            ->run(new StdioTransport());
    }
}
