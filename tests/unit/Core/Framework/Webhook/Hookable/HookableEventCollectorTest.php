<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Event\BusinessEventCollectorResponse;
use Contena\Core\Framework\Event\BusinessEventDefinition;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;
use Contena\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Contena\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Contena\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Contena\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[CoversClass(HookableEventCollector::class)]
class HookableEventCollectorTest extends TestCase
{
    private const MANIFEST_FIXTURE = __DIR__ . '/../../../../../integration/Core/Framework/App/Manifest/_fixtures/minimal/manifest.xml';

    private HookableEventCollector $hookableEventCollector;

    private BusinessEventCollector $businessEventCollector;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->businessEventCollector = static::createStub(BusinessEventCollector::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $hookableEntityDefinitions = [
            $this->createBlogDefinition(),
        ];

        $this->hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $this->definitionRegistry,
            new \ArrayIterator($hookableEntityDefinitions),
            new \ArrayIterator([new CoreHookableEventDescriber()])
        );
    }

    public function testGetHookableEventNamesWithPrivilegesReturnsCorrectStructure(): void
    {
        $context = Context::createDefaultContext();
        $result = $this->hookableEventCollector->getHookableEventNamesWithPrivileges($context, Manifest::createFromXmlFile(self::MANIFEST_FIXTURE));

        static::assertIsArray($result);
        static::assertNotCount(0, $result);

        // Check that we have entity written events
        static::assertArrayHasKey('blog.written', $result);
        static::assertArrayHasKey('blog.deleted', $result);
        static::assertArrayHasKey('test_entity.written', $result);
        static::assertArrayHasKey('test_entity.deleted', $result);

        // Check structure of entity written events
        static::assertArrayHasKey('privileges', $result['blog.written']);
        static::assertSame(['blog:read'], $result['blog.written']['privileges']);
        static::assertSame(['blog:read'], $result['blog.deleted']['privileges']);

        // Check that hookable events are included
        foreach (Hookable::HOOKABLE_EVENTS as $eventName) {
            static::assertArrayHasKey($eventName, $result);
            static::assertArrayHasKey('privileges', $result[$eventName]);
        }

        foreach (Hookable::HOOKABLE_EVENTS as $class => $eventName) {
            static::assertSame(Hookable::HOOKABLE_EVENTS_PRIVILEGES[$class], $result[$eventName]['privileges']);
        }
    }

    public function testGetPrivilegesFromBusinessEventDefinition(): void
    {
        $blogDefinition = $this->createBlogDefinition();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('get')->willReturn($blogDefinition);

        $hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $definitionRegistry,
            [],
            []
        );

        $businessEventDefinition = new BusinessEventDefinition(
            'test.event',
            'TestEvent',
            [
                'blog' => [
                    'type' => 'entity',
                    'entityClass' => BlogDefinition::class,
                ],
                'string' => [
                    'type' => 'string',
                    'name' => 'someString',
                ],
            ]
        );

        $result = $hookableEventCollector->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

        static::assertCount(1, $result);
        static::assertSame('blog:read', $result[0]);
    }

    public function testGetPrivilegesFromBusinessEventDefinitionWithNoEntityData(): void
    {
        $hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $this->definitionRegistry,
            [],
            []
        );

        $businessEventDefinition = new BusinessEventDefinition(
            'test.event',
            'TestEvent',
            [
                'string' => [
                    'type' => 'string',
                    'name' => 'someString',
                ],
                'array' => [
                    'type' => 'array',
                    'name' => 'someArray',
                ],
            ]
        );

        $result = $hookableEventCollector->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

        static::assertSame([], $result);
    }

    public function testGetEntityWrittenEventNamesWithPrivileges(): void
    {
        $result = $this->hookableEventCollector->getEntityWrittenEventNamesWithPrivileges();

        static::assertIsArray($result);

        // Check blog events
        static::assertArrayHasKey('blog.written', $result);
        static::assertArrayHasKey('blog.deleted', $result);
        static::assertSame(['privileges' => ['blog:read']], $result['blog.written']);
        static::assertSame(['privileges' => ['blog:read']], $result['blog.deleted']);

        // Check test entity events
        static::assertArrayHasKey('test_entity.written', $result);
        static::assertArrayHasKey('test_entity.deleted', $result);
        static::assertSame(['privileges' => ['test_entity:read']], $result['test_entity.written']);
        static::assertSame(['privileges' => ['test_entity:read']], $result['test_entity.deleted']);
    }

    public function testGetHookableEntities(): void
    {
        $result = $this->hookableEventCollector->getHookableEntities();

        static::assertIsArray($result);
        static::assertContains('blog', $result);
        static::assertContains('test_entity', $result);
        static::assertCount(2, $result);
    }

    public function testGetHookableEventNamesWithPrivilegesIncludesBusinessEvents(): void
    {
        $businessEventResponse = $this->createBusinessEventCollectorResponse();
        $businessEventCollector = static::createStub(BusinessEventCollector::class);
        $businessEventCollector->method('collect')->willReturn($businessEventResponse);

        $blogDefinition = $this->createBlogDefinition();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('get')->willReturn($blogDefinition);

        $hookableEventCollector = new HookableEventCollector(
            $businessEventCollector,
            $definitionRegistry,
            new \ArrayIterator([
                $this->createBlogDefinition(),
            ]),
            []
        );

        $context = Context::createDefaultContext();
        $result = $hookableEventCollector->getHookableEventNamesWithPrivileges($context, Manifest::createFromXmlFile(self::MANIFEST_FIXTURE));

        static::assertArrayHasKey('checkout.user.login', $result);
        static::assertArrayHasKey('privileges', $result['checkout.user.login']);
        static::assertSame(['blog:read'], $result['checkout.user.login']['privileges']);
    }

    public function testGetHookableEventNamesWithPrivilegesThrowsOnDuplicateDescribedEvents(): void
    {
        $hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $this->definitionRegistry,
            [],
            new \ArrayIterator([
            ])
        );

        $this->expectExceptionObject(
            WebhookException::duplicateDescribedEvent(
                'duplicate.described.event',
            )
        );

        $hookableEventCollector->getHookableEventNamesWithPrivileges(
            Context::createDefaultContext(),
            Manifest::createFromXmlFile(self::MANIFEST_FIXTURE)
        );
    }

    private function createBlogDefinition(): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('blog');

        return $definition;
    }

    private function createBusinessEventCollectorResponse(): BusinessEventCollectorResponse
    {
        $response = new BusinessEventCollectorResponse();

        $businessEvent = new BusinessEventDefinition(
            'checkout.user.login',
            'UserLoginEvent',
            [
                'entity' => [
                    'type' => 'entity',
                    'entityClass' => BlogDefinition::class,
                ],
            ]
        );

        $response->set('checkout.user.login', $businessEvent);

        return $response;
    }
}

// Create a test entity with the EntityAttribute for testing
/**
 * @internal Test fixture for entity with EntityAttribute
 */
#[EntityAttribute(name: 'test_entity')]
class TestEntityWithAttribute extends Entity
{
}

/**
 * @internal Test fixture
 */
class TestHookableEventDescriber implements HookableEventDescriber
{
    /**
     * @param list<string> $privileges
     */
    public function __construct(
        private readonly string $eventName,
        private readonly string $description,
        private readonly array $privileges
    ) {
    }

    public function describe(): array
    {
        return [];
    }

    public function describeForValidation(Manifest $manifest): array
    {
        return [
            new HookableEventDescription($this->eventName, $this->description, $this->privileges),
        ];
    }
}
