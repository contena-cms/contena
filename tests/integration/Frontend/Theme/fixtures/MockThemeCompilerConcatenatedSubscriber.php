<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\fixtures;

use Contena\Frontend\Event\ThemeCompilerConcatenatedStylesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MockThemeCompilerConcatenatedSubscriber implements EventSubscriberInterface
{
    final public const STYLES_CONCAT = '.mock-selector {}';

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeCompilerConcatenatedStylesEvent::class => 'onGetConcatenatedStyles',
        ];
    }

    public function onGetConcatenatedStyles(ThemeCompilerConcatenatedStylesEvent $event): void
    {
        $event->setConcatenatedStyles($event->getConcatenatedStyles() . self::STYLES_CONCAT);
    }
}
