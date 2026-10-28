<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Product\ProductFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class ProductFeatureSetPropertyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductFeatureSetFixtures;

    #[TestWith(['featureSet'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        $definition = static::getContainer()->get(ProductDefinition::class);

        static::assertTrue($definition->getFields()->has($field));
    }

    #[TestWith(['getFeatureSet'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(ProductEntity::class, $method));
    }

    #[TestWith(['FeatureSetBasic'])]
    #[TestWith(['FeatureSetComplete'])]
    public function testFeatureSetsCanBeCreated(string $type): void
    {
        $this->getFeatureSetFixture($type);
    }
}
