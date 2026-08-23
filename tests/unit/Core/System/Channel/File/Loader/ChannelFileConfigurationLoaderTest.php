<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileEntity;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ChannelFileConfigurationLoader::class)]
class ChannelFileConfigurationLoaderTest extends TestCase
{
    public function testLoadReturnsConfiguredFileForChannelFamilyAndFileName(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $configuration = $this->createConfiguration($channelId, 'agentic', 'llms.txt');

        $repository = StaticEntityRepository::of(ChannelFileCollection::class, [
            function (Criteria $criteria, Context $searchContext) use ($context, $channelId, $configuration): ChannelFileCollection {
                static::assertSame($context, $searchContext);
                static::assertSame(1, $criteria->getLimit());
                $this->assertEqualsFilters($criteria, [
                    'channelId' => $channelId,
                    'fileFamily' => 'agentic',
                    'fileName' => 'llms.txt',
                ]);

                return new ChannelFileCollection([$configuration]);
            },
        ]);

        $result = new ChannelFileConfigurationLoader($repository)->load('agentic', 'llms.txt', $channelId, $context);

        static::assertSame($configuration, $result);
    }

    public function testLoadForFileFamilyReturnsConfigurationsKeyedByFileName(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $llms = $this->createConfiguration($channelId, 'agentic', 'llms.txt');
        $agents = $this->createConfiguration($channelId, 'agentic', 'AGENTS.md');

        $repository = StaticEntityRepository::of(ChannelFileCollection::class, [
            function (Criteria $criteria, Context $searchContext) use ($context, $channelId, $llms, $agents): ChannelFileCollection {
                static::assertSame($context, $searchContext);
                static::assertNull($criteria->getLimit());
                $this->assertEqualsFilters($criteria, [
                    'channelId' => $channelId,
                    'fileFamily' => 'agentic',
                ]);

                return new ChannelFileCollection([$llms, $agents]);
            },
        ]);

        $result = new ChannelFileConfigurationLoader($repository)->loadForFileFamily('agentic', $channelId, $context);

        static::assertSame([
            'llms.txt' => $llms,
            'agents.md' => $agents,
        ], $result);
    }

    /**
     * @param array<string, string> $expected
     */
    private function assertEqualsFilters(Criteria $criteria, array $expected): void
    {
        $filters = [];

        foreach ($criteria->getFilters() as $filter) {
            static::assertInstanceOf(EqualsFilter::class, $filter);
            $filters[$filter->getField()] = $filter->getValue();
        }

        static::assertSame($expected, $filters);
    }

    private function createConfiguration(string $channelId, string $fileFamily, string $fileName): ChannelFileEntity
    {
        $configuration = new ChannelFileEntity();
        $configuration->setId(Uuid::randomHex());
        $configuration->setChannelId($channelId);
        $configuration->setFileFamily($fileFamily);
        $configuration->setFileName($fileName);

        return $configuration;
    }
}
