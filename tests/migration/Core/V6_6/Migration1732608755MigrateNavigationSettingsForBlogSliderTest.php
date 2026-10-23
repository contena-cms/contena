<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1732608755MigrateNavigationSettingsForBlogSlider;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 *
 * @phpstan-type cmsConfigProperty array{value: mixed, source: string}
 */
#[CoversClass(Migration1732608755MigrateNavigationSettingsForBlogSlider::class)]
class Migration1732608755MigrateNavigationSettingsForBlogSliderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1732608755MigrateNavigationSettingsForBlogSlider();
        static::assertSame(1732608755, $migration->getCreationTimestamp());
    }

    #[DataProvider('blogSliderConfigDataProvider')]
    public function testMigrationUpdatesBlogSliderConfigWithNavigation(bool $hasNavigation): void
    {
        $affectedSlotId = $this->prepareOldDatabaseEntry($hasNavigation);

        $migration = new Migration1732608755MigrateNavigationSettingsForBlogSlider();
        $migration->update($this->connection);

        $expectedConfig = $this->getExpectedBlogSliderConfig($hasNavigation);
        $actualConfig = $this->getActualConfig($affectedSlotId);

        ksort($expectedConfig);
        ksort($actualConfig);

        static::assertSame($expectedConfig, $actualConfig);
    }

    /**
     * @return iterable<string, list<bool>>
     */
    public static function blogSliderConfigDataProvider(): iterable
    {
        yield 'config had navigation' => [true];
        yield 'config had no navigation' => [false];
    }

    /**
     * @return array<string, cmsConfigProperty>
     */
    public function getActualConfig(string $affectedSlotId): array
    {
        $cmsSlotRepository = static::getContainer()->get('cms_slot.repository');
        $criteria = new Criteria();
        $criteria->setIds([$affectedSlotId]);

        /** @var CmsSlotCollection $cmsSlots */
        $cmsSlots = $cmsSlotRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var CmsSlotEntity $cmsSlot */
        $cmsSlot = $cmsSlots->first();

        /** @var array<string, cmsConfigProperty> $cmsSlotConfig */
        $cmsSlotConfig = $cmsSlot->getConfig();

        return $cmsSlotConfig;
    }

    private function prepareOldDatabaseEntry(bool $hasNavigation): string
    {
        $id = Uuid::randomHex();

        $cmsPageRepository = static::getContainer()->get('cms_page.repository');
        $cmsPageRepository->create(
            [[
                'id' => $id,
                'type' => 'page',
                'sections' => [[
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [[
                        'type' => 'blog-slider',
                        'position' => 0,
                        'slots' => [[
                            'id' => $id,
                            'type' => 'blog-slider',
                            'slot' => 'content',
                            'config' => $this->getActualSampleBlogSliderConfig($hasNavigation),
                        ]],
                    ]],
                ]],
            ]],
            Context::createDefaultContext(),
        );

        return $id;
    }

    /**
     * @return array<string, cmsConfigProperty>
     */
    private function getActualSampleBlogSliderConfig(bool $hasNavigation): array
    {
        return [
            'navigation' => [
                'value' => $hasNavigation,
                'source' => 'static',
            ],
            'title' => [
                'value' => 'demo',
                'source' => 'static',
            ],
            'border' => [
                'value' => false,
                'source' => 'static',
            ],
            'rotate' => [
                'value' => false,
                'source' => 'static',
            ],
            'blogs' => [
                'value' => [],
                'source' => 'static',
            ],
            'boxLayout' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'elMinWidth' => [
                'value' => '300px',
                'source' => 'static',
            ],
            'displayMode' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'verticalAlign' => [
                'value' => null,
                'source' => 'static',
            ],
            'contentStreamLimit' => [
                'value' => 10,
                'source' => 'static',
            ],
            'contentStreamSorting' => [
                'value' => 'name:ASC',
                'source' => 'static',
            ],
        ];
    }

    /**
     * @return array<string, cmsConfigProperty>
     */
    private function getExpectedBlogSliderConfig(bool $hasNavigation): array
    {
        return [
            'navigationArrows' => [
                'value' => $hasNavigation ? 'outside' : 'none',
                'source' => 'static',
            ],
            'title' => [
                'value' => 'demo',
                'source' => 'static',
            ],
            'border' => [
                'value' => false,
                'source' => 'static',
            ],
            'rotate' => [
                'value' => false,
                'source' => 'static',
            ],
            'blogs' => [
                'value' => [],
                'source' => 'static',
            ],
            'boxLayout' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'elMinWidth' => [
                'value' => '300px',
                'source' => 'static',
            ],
            'displayMode' => [
                'value' => 'standard',
                'source' => 'static',
            ],
            'verticalAlign' => [
                'value' => null,
                'source' => 'static',
            ],
            'contentStreamLimit' => [
                'value' => 10,
                'source' => 'static',
            ],
            'contentStreamSorting' => [
                'value' => 'name:ASC',
                'source' => 'static',
            ],
        ];
    }
}
