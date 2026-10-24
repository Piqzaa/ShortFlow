<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BuildAssetExtension extends AbstractExtension
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('build_asset', $this->url(...))];
    }

    public function url(string $name): string
    {
        if (!in_array($name, ['app.js', 'app.css'], true)) {
            throw new \InvalidArgumentException('Unknown build asset.');
        }
        $file = $this->projectDir.'/public/build/'.$name;
        $hash = is_file($file) ? hash_file('sha256', $file) : false;

        return '/build/'.$name.(false === $hash ? '' : '?v='.substr($hash, 0, 16));
    }
}
