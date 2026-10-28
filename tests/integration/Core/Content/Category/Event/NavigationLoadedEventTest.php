<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Category\Event;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Event\NavigationLoadedEvent;
use Contena\Core\Content\Category\Service\NavigationLoader;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class NavigationLoadedEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected NavigationLoaderInterface $loader;

    protected function setUp(): void
    {
        $this->loader = static::getContainer()->get(NavigationLoader::class);
        parent::setUp();
    }

    public function testEventDispatched(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        EventHookDispatcher::fromContainer(static::getContainer())->on(NavigationLoadedEvent::class, $listener);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        $this->loader->load($navigationId, $context, $navigationId);
    }
}
