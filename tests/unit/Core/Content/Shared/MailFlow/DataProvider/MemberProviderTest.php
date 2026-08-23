<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberProvider;
use Contena\Core\System\Member\MemberDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<MemberProvider>
 */
#[CoversClass(MemberProvider::class)]
class MemberProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): MemberProvider {
        return new MemberProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return MemberDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'addresses.country',
            'addresses.region',
        ];
    }
}
