<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\ArrayBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\CollectionBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\EntityBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\HiddenEntityBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\NestedEntityBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\ScalarBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\StructuredArrayObjectBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\StructuredObjectBusinessEvent;
use Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\UnstructuredObjectBusinessEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\BusinessEventEncoder;
use Contena\Core\Framework\Webhook\Hookable\HookableBusinessEvent;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HookableBusinessEvent::class)]
class HookableBusinessEventTest extends TestCase
{
    public function testGetter(): void
    {
        $scalarEvent = new ScalarBusinessEvent();
        $event = HookableBusinessEvent::fromBusinessEvent(
            $scalarEvent,
            new BusinessEventEncoder(
                static::createStub(JsonEntityEncoder::class),
                static::createStub(DefinitionInstanceRegistry::class)
            )
        );

        static::assertSame($scalarEvent->getName(), $event->getName());
        static::assertSame($scalarEvent->getEncodeValues(''), $event->getWebhookPayload());
    }

    #[DataProvider('getEventsWithoutPermissions')]
    public function testIsAllowedForNonEntityBasedEvents(FlowEventAware $rootEvent): void
    {
        $event = HookableBusinessEvent::fromBusinessEvent(
            $rootEvent,
            static::createStub(BusinessEventEncoder::class)
        );

        static::assertTrue($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }

    /**
     * @return array<array{0: FlowEventAware}>
     */
    public static function getEventsWithoutPermissions(): array
    {
        return [
            [new ScalarBusinessEvent()],
            [new StructuredObjectBusinessEvent()],
            [new StructuredArrayObjectBusinessEvent()],
            [new UnstructuredObjectBusinessEvent()],
        ];
    }

    public function testHiddenFromWebhookEntityDoesNotRequirePrivilege(): void
    {
        $tax = new TaxEntity();
        $tax->setId('tax-id');

        $event = HookableBusinessEvent::fromBusinessEvent(
            new HiddenEntityBusinessEvent($tax),
            static::createStub(BusinessEventEncoder::class)
        );

        // the entity is hidden from webhooks, so its read privilege is not enforced either
        static::assertTrue($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }

    #[DataProvider('getEventsWithPermissions')]
    public function testIsAllowedForEntityBasedEvents(FlowEventAware $rootEvent): void
    {
        $event = HookableBusinessEvent::fromBusinessEvent(
            $rootEvent,
            static::createStub(BusinessEventEncoder::class)
        );

        $allowedPermissions = new AclPrivilegeCollection([
            TaxDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertTrue($event->isAllowed(Uuid::randomHex(), $allowedPermissions));

        $notAllowedPermissions = new AclPrivilegeCollection([
            ProductDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertFalse($event->isAllowed(Uuid::randomHex(), $notAllowedPermissions));
    }

    /**
     * @return array<array{0: FlowEventAware}>
     */
    public static function getEventsWithPermissions(): array
    {
        $tax = new TaxEntity();
        $tax->setId('tax-id');
        $tax->setName('test');
        $tax->setTaxRate(19);
        $tax->setPosition(1);

        return [
            [new EntityBusinessEvent($tax)],
            [new CollectionBusinessEvent(new TaxCollection([$tax]))],
            [new ArrayBusinessEvent(new TaxCollection([$tax]))],
            [new NestedEntityBusinessEvent($tax)],
        ];
    }
}
