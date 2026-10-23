<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Blog\BlogFeatureSet;

use PHPUnit\Framework\Attributes\Before;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\Traits\EntityFixturesBase;

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



}
