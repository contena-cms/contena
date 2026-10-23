<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\NavigationRouteResponse;
use Contena\Core\Content\Category\Extension\NavigationRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationRouteExtension::class)]
class NavigationRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesNavigation(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new NavigationRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: NavigationRouteExtension::NAME,
            extension: new NavigationRouteExtension(
                'active-id',
                'root-id',
                new Request(),
                Generator::generateChannelContext(),
                new Criteria(),
            ),
            function: static function () use (&$coreCalled): NavigationRouteResponse {
                $coreCalled = true;

                return new NavigationRouteResponse(new CategoryCollection([
                    (new CategoryEntity())->assign(['id' => 'core-category']),
                ]));
            },
        );

        static::assertFalse($coreCalled, 'The core navigation loading must be skipped when a subscriber resolves it.');
        static::assertSame(['example-category'], array_values($result->getCategories()->getIds()));
    }
}
