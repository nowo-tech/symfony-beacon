<?php

declare(strict_types=1);

$finder = new TwigCsFixer\File\Finder();
$finder->in(__DIR__.'/templates');

$ruleset = new TwigCsFixer\Ruleset\Ruleset();
$ruleset->addStandard(new TwigCsFixer\Standard\Symfony());

$config = new TwigCsFixer\Config\Config();
$config->setFinder($finder);
$config->setRuleset($ruleset);
$config->setCacheFile(__DIR__.'/.twig-cs-fixer.cache');

return $config;
