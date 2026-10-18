<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class OAuthCallbackOrigin
{
    public function assertSameOrigin(Request $request, string $callback): void
    {
        $parts = parse_url($callback);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new PublicationException('L’adresse de retour de ce réseau doit être configurée avant la connexion.');
        }
        $expected = Request::create($callback)->getSchemeAndHttpHost();
        if ($expected !== $request->getSchemeAndHttpHost()) {
            throw new PublicationException('Pour connecter ce réseau, ouvre '.$expected.'/studio/connexions puis recommence. La connexion et son retour doivent utiliser la même adresse VydFlow.');
        }
    }
}
