<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Blog\BlogFeatureSet;

use PHPUnit\Framework\Attributes\Before;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
trait BlogFeatureSetFixtures
{
    use EntityFixturesBase;

    /**
     * @var array<mixed>
     */
    public array $featureSetFixtures;

    #[Before]
    public function initializeFeatureSetFixtures(): void
    {
        $this->featureSetFixtures = [
            'FeatureSetBasic' => [
                'id' => Uuid::randomHex(),
                'name' => 'Basic',
                'description' => 'This is a basic template entity',
                'features' => [],
            ],
            'FeatureSetComplete' => [
                'id' => Uuid::randomHex(),
                'name' => 'Template with features',
                'description' => 'This template contains features',
                'features' => [
                    [
                        'id' => null,
                        'name' => null,
                        'position' => 1,
                    ],
                    [
                        'id' => null,
                        'name' => 'description',
                        'position' => 2,
                    ],
                ],
            ],
        ];
    }

    public function getBasicFeatureSet(): BlogFeatureSetEntity
    {
        return $this->getFeatureSetFixture('FeatureSetBasic');
    }

    public function getCompleteFeatureSet(): BlogFeatureSetEntity
    {
        return $this->getFeatureSetFixture('FeatureSetComplete');
    }

    private function getFeatureSetFixture(string $fixtureName): BlogFeatureSetEntity
    {
        $template = $this->createFixture(
            $fixtureName,
            $this->featureSetFixtures,
            self::getFixtureRepository(BlogFeatureSetDefinition::ENTITY_NAME)
        );

        static::assertInstanceOf(BlogFeatureSetEntity::class, $template);

        return $template;
    }
}
