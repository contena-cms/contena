<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle;

use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;

/**
 * @internal
 */
class ExampleBundle extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
        ];
    }
}
