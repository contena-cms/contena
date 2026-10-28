<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\fixtures\PluginWithAdditionalBundles;

use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;

/**
 * @internal
 */
class PluginWithAdditionalBundles extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $additionalBundleParameters): array
    {
        return [
        ];
    }
}
