<?php
$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/migrations', __DIR__.'/docker/php']);
return (new PhpCsFixer\Config())->setRules(['@Symfony' => true])->setFinder($finder)->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig(1, 20));
