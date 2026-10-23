<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Contena\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[CoversClass(HookableEntityWrittenEvent::class)]
class HookableEntityWrittenEventTest extends TestCase
{
    public function testGetter(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent($entityId));

        static::assertSame('blog.written', $event->getName());
        static::assertSame([
            [
                'entity' => 'blog',
                'operation' => 'delete',
                'primaryKey' => $entityId,
                'updatedFields' => [],
            ],
        ], $event->getWebhookPayload(new AppEntity()));
    }

    public function testGetterWithVersionId(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent(
            $entityId,
            ['versionId' => Defaults::LIVE_VERSION]
        ));

        static::assertSame('blog.written', $event->getName());
        static::assertSame([
            [
                'entity' => 'blog',
                'operation' => 'delete',
                'primaryKey' => $entityId,
                'updatedFields' => ['versionId'],
                'versionId' => Defaults::LIVE_VERSION,
            ],
        ], $event->getWebhookPayload(new AppEntity()));
    }

    public function testIsAllowed(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent($entityId));

        $allowedPermissions = new AclPrivilegeCollection([
            BlogDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertTrue($event->isAllowed(
            Uuid::randomHex(),
            $allowedPermissions
        ));

        $notAllowedPermissions = new AclPrivilegeCollection([
            UserDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertFalse($event->isAllowed(
            Uuid::randomHex(),
            $notAllowedPermissions
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getEntityWrittenEvent(string $entityId, array $payload = []): EntityWrittenEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenEvent(
            BlogDefinition::ENTITY_NAME,
            [
            ],
            $context
        );
    }
}
