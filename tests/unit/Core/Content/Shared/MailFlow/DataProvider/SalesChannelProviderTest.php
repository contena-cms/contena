<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<SalesChannelProvider>
 */
#[Package('after-sales')]
#[CoversClass(SalesChannelProvider::class)]
class SalesChannelProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): SalesChannelProvider {
        return new SalesChannelProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'domains',
            'mailHeaderFooter',
        ];
    }
}
