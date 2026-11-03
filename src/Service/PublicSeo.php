<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicSeo
{
    public const ROUTES = ['app_home', 'app_guide', 'app_contact'];

    public function __construct(
        private UrlGeneratorInterface $router,
        #[Autowire('%env(SEO_PUBLIC_ORIGIN)%')] private string $origin,
        #[Autowire('%env(bool:SEO_INDEXING_ENABLED)%')] private bool $enabled,
    ) {
    }

    public function canonical(string $route): ?string
    {
        return in_array($route, self::ROUTES, true) ? rtrim($this->origin, '/').$this->router->generate($route) : null;
    }

    public function indexable(Request $request): bool
    {
        return $this->enabled && $request->isMethodSafe()
            && $request->getSchemeAndHttpHost() === rtrim($this->origin, '/')
            && in_array($request->attributes->get('_route'), self::ROUTES, true);
    }
}
