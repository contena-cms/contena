<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberRecoveryProvider;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<MemberRecoveryProvider>
 */
#[CoversClass(MemberRecoveryProvider::class)]
class MemberRecoveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): MemberRecoveryProvider {
        return new MemberRecoveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return MemberRecoveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['member'];
    }
}
