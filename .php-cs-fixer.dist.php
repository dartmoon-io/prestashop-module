<?php

$config = new PrestaShop\CodingStandards\CsFixer\Config();

$config
    ->setUsingCache(true)
    ->getFinder()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('vendor-prefixed')
    ->exclude('vendor-bin')
    ->exclude('.pbt')
    ->notName('scoper.inc.php');

return $config;
