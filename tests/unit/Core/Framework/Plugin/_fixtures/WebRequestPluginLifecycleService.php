<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\_fixtures;

use Contena\Core\Framework\Plugin\PluginLifecycleService;

/**
 * Behaves as if the service runs in a web request, where the composer removal of an uninstalled plugin is deferred
 * to the response.
 *
 * @internal
 */
class WebRequestPluginLifecycleService extends PluginLifecycleService
{
    protected function isCLI(): bool
    {
        return false;
    }
}
