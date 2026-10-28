<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;
use Contena\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal
 */
#[Package('framework')]
class HookableEventCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MANIFEST_FIXTURE = __DIR__ . '/../../App/Manifest/_fixtures/minimal/manifest.xml';

    private HookableEventCollector $hookableEventCollector;

    protected function setUp(): void
    {
        $this->hookableEventCollector = static::getContainer()->get(HookableEventCollector::class);
    }

    public function testGetHookableEventNamesWithPrivileges(): void
    {
        $hookableEventNamesWithPrivileges = $this->hookableEventCollector->getHookableEventNamesWithPrivileges(
            Context::createDefaultContext(),
            Manifest::createFromXmlFile(self::MANIFEST_FIXTURE)
        );
        static::assertNotCount(0, $hookableEventNamesWithPrivileges);

        foreach ($hookableEventNamesWithPrivileges as $key => $hookableEventNamesWithPrivilege) {
            static::assertIsArray($hookableEventNamesWithPrivilege);
            static::assertIsString($key);
            static::assertArrayHasKey('privileges', $hookableEventNamesWithPrivilege);
        }
    }

    public function testGetHookableEntities(): void
    {
        $hookableEntities = $this->hookableEventCollector->getHookableEntities();
        static::assertNotCount(0, $hookableEntities);

        static::assertContains(ProductDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(ProductPriceDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(MediaDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(CategoryDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(CustomerDefinition::ENTITY_NAME, $hookableEntities);
    }

    public function testGetHookableEntitiesWithEntityWithAttribute(): void
    {
        // Create test entity with EntityAttribute
        $testEntity = new TestEntityWithAttribute();

        $collector = new HookableEventCollector(
            static::getContainer()->get(BusinessEventCollector::class),
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            new \ArrayIterator([$testEntity]),
            new \ArrayIterator([static::getContainer()->get(CoreHookableEventDescriber::class)])
        );

        $entities = $collector->getHookableEntities();

        static::assertContains('test_entity_with_attr', $entities);
        static::assertCount(1, $entities);
    }
}

/**
 * @internal Test fixture for entity with EntityAttribute
 */
#[EntityAttribute('test_entity_with_attr')]
class TestEntityWithAttribute extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;
}
