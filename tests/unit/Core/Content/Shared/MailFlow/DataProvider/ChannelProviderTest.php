<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ChannelProvider;
use Contena\Core\System\Channel\ChannelDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<ChannelProvider>
 */
#[CoversClass(ChannelProvider::class)]
class ChannelProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): ChannelProvider {
        return new ChannelProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return ChannelDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'domains',
            'mailHeaderFooter',
        ];
    }
}
