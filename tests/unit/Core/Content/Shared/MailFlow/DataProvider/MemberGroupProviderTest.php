<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberGroupProvider;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<MemberGroupProvider>
 */
#[CoversClass(MemberGroupProvider::class)]
class MemberGroupProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): MemberGroupProvider {
        return new MemberGroupProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return MemberGroupDefinition::ENTITY_NAME;
    }
}
