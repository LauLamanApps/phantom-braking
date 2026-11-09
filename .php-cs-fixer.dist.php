<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->notPath('config/reference.php')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR1' => true,
    '@PSR2' => true,
    '@PSR12' => true,
    '@Symfony' => true,
    '@DoctrineAnnotation' => true,
    'array_syntax' => ['syntax' => 'short'],
])->setFinder($finder);
