<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\fixtures\PluginWithAdditionalBundles;

use Contena\Core\Framework\Parameter\AdditionalBundleParameters;
use Contena\Core\Framework\Plugin;
use Contena\Tests\Unit\Frontend\Theme\fixtures\PluginWithAdditionalBundles\SubBundle1\SubBundle1;

/**
 * @internal
 */
class PluginWithAdditionalBundles extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $additionalBundleParameters): array
    {
        return [
            new SubBundle1(),
        ];
    }
}
