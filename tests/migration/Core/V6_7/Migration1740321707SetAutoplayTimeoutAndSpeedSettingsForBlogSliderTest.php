<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

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
use Contena\Core\Migration\V6_7\Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForBlogSlider;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 *
 * @phpstan-type cmsConfigProperty array{value: mixed, source: string}
 */
#[CoversClass(Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForBlogSlider::class)]
class Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForBlogSliderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForBlogSlider();
        static::assertSame(1740321707, $migration->getCreationTimestamp());
    }

    #[DataProvider('blogSliderConfigDataProvider')]
    public function testMigrationUpdatesBlogSliderConfigWithAutoplayTimeoutAndSpeed(?int $autoplayTimeout, ?int $speed): void
    {
        $affectedSlotId = $this->prepareOldDatabaseEntry($autoplayTimeout, $speed);

        $migration = new Migration1740321707SetAutoplayTimeoutAndSpeedSettingsForBlogSlider();
        $migration->update($this->connection);

        $expectedConfig = $this->getExpectedBlogSliderConfig($autoplayTimeout, $speed);
        $actualConfig = $this->getActualConfig($affectedSlotId);

        ksort($expectedConfig);
        ksort($actualConfig);

        static::assertSame($expectedConfig, $actualConfig);
    }

    /**
     * @return iterable<string, list<int|null>>
     */
    public static function blogSliderConfigDataProvider(): iterable
    {
        yield 'config with autoplay timeout and speed' => [10000, 500];
        yield 'config with autoplay timeout' => [10000, null];
        yield 'config with speed' => [null, 500];
        yield 'config without autoplay timeout and speed' => [null, null];
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

    private function prepareOldDatabaseEntry(?int $autoplayTimeout, ?int $speed): string
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
                            'config' => $this->getActualSampleBlogSliderConfig($autoplayTimeout, $speed),
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
    private function getActualSampleBlogSliderConfig(?int $autoplayTimeout, ?int $speed): array
    {
        return [
            ...($autoplayTimeout !== null ? ['autoplayTimeout' => [
                'value' => $autoplayTimeout,
                'source' => 'static',
            ]] : []),
            ...($speed !== null ? ['speed' => [
                'value' => $speed,
                'source' => 'static',
            ]] : []),
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
            'navigationArrows' => [
                'value' => 'outside',
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
    private function getExpectedBlogSliderConfig(?int $autoplayTimeout, ?int $speed): array
    {
        return [
            'autoplayTimeout' => [
                'value' => $autoplayTimeout ?? 5000,
                'source' => 'static',
            ],
            'speed' => [
                'value' => $speed ?? 300,
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
            'navigationArrows' => [
                'value' => 'outside',
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
