<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Theme\fixtures;

use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MockThemeVariablesSubscriber implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfig;

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeCompilerEnrichScssVariablesEvent::class => 'onAddVariables',
        ];
    }

}
