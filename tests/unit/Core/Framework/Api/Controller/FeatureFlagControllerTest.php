<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Api\Controller\FeatureFlagController;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;

/**
 * @internal
 */
#[CoversClass(FeatureFlagController::class)]
class FeatureFlagControllerTest extends TestCase
{
    public function testEnable(): void
    {
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects($this->once())->method('enable')->with('foo');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');

        $controller = new FeatureFlagController($featureFlagService, $cacheClearer);
        $controller->enable('foo');
    }

    public function testDisable(): void
    {
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects($this->once())->method('disable')->with('foo');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('clear');

        $controller = new FeatureFlagController($featureFlagService, $cacheClearer);
        $controller->disable('foo');
    }

    public function testLoad(): void
    {
        $featureFlags = [
            'FOO' => [
                'name' => 'Foo',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'major' => true,
                'description' => 'This is a test feature',
            ],
            'BAR' => [
                'name' => 'Bar',
                'default' => true,
                'toggleable' => true,
                'active' => false,
                'major' => false,
                'description' => 'This is another test feature',
            ],
            'NEWFEATURE' => [
                'name' => 'newFeature',
                'default' => true,
                'toggleable' => true,
                'major' => false,
                'description' => 'This is new test feature',
            ],
        ];

        Feature::registerFeatures($featureFlags);

        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects($this->never())->method('disable')->with('foo');

        $controller = new FeatureFlagController(
            $featureFlagService,
            static::createStub(CacheClearer::class)
        );

        $response = $controller->load();

        $expectedFeatureFlags = $featureFlags;
        $expectedFeatureFlags['NEWFEATURE']['active'] = true;

        static::assertSame($expectedFeatureFlags, json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
