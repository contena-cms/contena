<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Notification\NotificationCollection;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\Framework\Notification\NotificationEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(NotificationDefinition::class)]
class NotificationDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(NotificationDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(NotificationEntity::class, $definition->getEntityClass());
        static::assertSame(NotificationCollection::class, $definition->getCollectionClass());
        static::assertSame('6.4.7.0', $definition->since());
        static::assertNotSame([], $definition->getDefaults());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): NotificationDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [NotificationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(NotificationDefinition::ENTITY_NAME);
        static::assertInstanceOf(NotificationDefinition::class, $definition);

        return $definition;
    }
}
