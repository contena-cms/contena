<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\BlogFeatureSet;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Test\Blog\BlogFeatureSet\BlogFeatureSetFixtures;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogFeatureSetPropertyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use BlogFeatureSetFixtures;

    #[TestWith(['featureSet'])]
    public function testDefinitionFieldsAreComplete(string $field): void
    {
        $definition = static::getContainer()->get(BlogDefinition::class);

        static::assertTrue($definition->getFields()->has($field));
    }

    #[TestWith(['getFeatureSet'])]
    public function testEntityIsComplete(string $method): void
    {
        static::assertTrue(method_exists(BlogEntity::class, $method));
    }

    #[TestWith(['FeatureSetBasic'])]
    #[TestWith(['FeatureSetComplete'])]
    public function testFeatureSetsCanBeCreated(string $type): void
    {
        $this->getFeatureSetFixture($type);
    }
}
