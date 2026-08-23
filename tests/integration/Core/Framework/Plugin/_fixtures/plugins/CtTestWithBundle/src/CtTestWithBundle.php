<?php declare(strict_types=1);

namespace CtTestWithBundle;

use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;
use Contena\Tests\Integration\Core\Framework\Plugin\_fixtures\bundles\FooBarBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

class CtTestWithBundle extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        require_once __DIR__ . '/../../../bundles/FooBarBundle.php';

        return [
            // is already provided externally and should not be loaded
            new FrameworkBundle(),
            // is already provided by CtTest and should not be loaded twice
            new FooBarBundle(),
        ];
    }
}
