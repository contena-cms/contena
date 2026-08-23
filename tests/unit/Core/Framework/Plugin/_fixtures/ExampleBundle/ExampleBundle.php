<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle;

use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;
use Contena\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\FeatureA\FeatureA;
use Contena\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\FeatureB\FeatureB;

/**
 * @internal
 */
class ExampleBundle extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            new FeatureA(),
            new FeatureB(),
        ];
    }
}
