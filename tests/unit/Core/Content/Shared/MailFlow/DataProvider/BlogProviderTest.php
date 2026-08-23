<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<BlogProvider>
 */
#[CoversClass(BlogProvider::class)]
class BlogProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): BlogProvider {
        return new BlogProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return BlogDefinition::ENTITY_NAME;
    }
}
