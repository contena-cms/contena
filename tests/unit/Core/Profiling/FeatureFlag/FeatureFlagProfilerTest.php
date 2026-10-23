<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Profiling\FeatureFlag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Profiling\FeatureFlag\FeatureFlagProfiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(FeatureFlagProfiler::class)]
class FeatureFlagProfilerTest extends TestCase
{
    public function testCollect(): void
    {
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'name' => 'Feature ABC',
                'default' => true,
                'description' => 'This is a test feature',
            ],
        ]);
        $featureFlagService = $this->createMock(FeatureFlagRegistry::class);
        $featureFlagService->expects($this->once())->method('register');

        $profiler = new FeatureFlagProfiler($featureFlagService);
        $profiler->collect(new Request(), new Response());

        static::assertSame('feature_flag', $profiler->getName());
        static::assertSame('@Profiling/Collector/flags.html.twig', FeatureFlagProfiler::getTemplate());
        static::assertSame([
            'FEATURE_ABC' => [
                'name' => 'FEATURE_ABC',
                'default' => true,
                'description' => 'This is a test feature',
                'active' => true,
            ],
        ], $profiler->getFeatures());
    }

    public function testGetTemplate(): void
    {
        static::assertSame('@Profiling/Collector/flags.html.twig', FeatureFlagProfiler::getTemplate());
    }

    public function testGetName(): void
    {
        $featureFlagService = static::createStub(FeatureFlagRegistry::class);
        $profiler = new FeatureFlagProfiler($featureFlagService);
        static::assertSame('feature_flag', $profiler->getName());
    }
}
