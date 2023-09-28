<?php

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests');

return (new PhpCsFixer\Config())->setRules([
        '@Symfony' => true,
        'increment_style' => ['style' => 'post'],
        'yoda_style' => false,
    ])
    ->setFinder($finder);
