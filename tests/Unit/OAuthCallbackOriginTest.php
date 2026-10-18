<?php

namespace App\Tests\Unit;

use App\Service\OAuthCallbackOrigin;
use App\Service\PublicationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class OAuthCallbackOriginTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function mismatches(): iterable
    {
        yield 'tunnel to localhost' => ['https://vydflow.example.test/studio/connexions', 'http://localhost:8000/studio/connexions/youtube/retour'];
        yield 'localhost to tunnel' => ['http://localhost:8000/studio/connexions', 'https://vydflow.example.test/studio/connexions/instagram/retour'];
        yield 'different scheme' => ['http://vydflow.example.test/studio/connexions', 'https://vydflow.example.test/studio/connexions/instagram/retour'];
        yield 'different port' => ['http://localhost:8001/studio/connexions', 'http://localhost:8000/studio/connexions/youtube/retour'];
    }

    #[DataProvider('mismatches')]
    public function testDifferentOriginCannotStartOAuth(string $request, string $callback): void
    {
        $this->expectException(PublicationException::class);
        $this->expectExceptionMessage('La connexion et son retour doivent utiliser la même adresse');
        (new OAuthCallbackOrigin())->assertSameOrigin(Request::create($request), $callback);
    }

    public function testSameOriginAllowsDifferentPathsAndDefaultHttpsPort(): void
    {
        $guard = new OAuthCallbackOrigin();
        $guard->assertSameOrigin(Request::create('https://vydflow.example.test/studio/connexions/instagram'), 'https://vydflow.example.test:443/studio/connexions/instagram/retour');
        $guard->assertSameOrigin(Request::create('http://localhost:8000/studio/connexions/youtube'), 'http://localhost:8000/studio/connexions/youtube/retour');
        $this->addToAssertionCount(2);
    }
}
