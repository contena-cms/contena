<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\File\Loader;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelFileConfigurationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItLoadsConfigurationForFile(): void
    {
        $id = Uuid::randomHex();
        $fileName = 'custom-' . Uuid::randomHex() . '.txt';

        $this->getChannelFileRepository()->create([
            [
                'id' => $id,
                'channelId' => TestDefaults::CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => $fileName,
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'channel owner override',
                ],
            ],
        ], Context::createDefaultContext());

        $configuration = $this->getConfigurationLoader()->load(
            'agentic',
            $fileName,
            TestDefaults::CHANNEL,
            Context::createDefaultContext()
        );

        static::assertNotNull($configuration);
        static::assertSame($id, $configuration->getId());
        static::assertSame(TestDefaults::CHANNEL, $configuration->getChannelId());
        static::assertSame('agentic', $configuration->getFileFamily());
        static::assertSame($fileName, $configuration->getFileName());
        static::assertTrue($configuration->isEnabled());
        static::assertSame(['Framework' => 'channel owner override'], $configuration->getTemplateOverrides());
    }

    public function testItLoadsConfigurationsForFileFamilyIndexedByFileName(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $otherFamilyId = Uuid::randomHex();
        $suffix = Uuid::randomHex();
        $firstFileName = 'custom-' . $suffix . '-first.txt';
        $secondFileName = 'custom-' . $suffix . '-second.txt';
        $otherFamilyFileName = 'robots-' . $suffix . '.txt';

        $this->getChannelFileRepository()->create([
            [
                'id' => $firstId,
                'channelId' => TestDefaults::CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => $firstFileName,
                'enabled' => true,
                'templateOverrides' => [],
            ],
            [
                'id' => $secondId,
                'channelId' => TestDefaults::CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => $secondFileName,
                'enabled' => false,
                'templateOverrides' => [
                    'Ucp' => 'plugin override',
                ],
            ],
            [
                'id' => $otherFamilyId,
                'channelId' => TestDefaults::CHANNEL,
                'fileFamily' => 'seo',
                'fileName' => $otherFamilyFileName,
                'enabled' => true,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $configurations = $this->getConfigurationLoader()->loadForFileFamily(
            'agentic',
            TestDefaults::CHANNEL,
            Context::createDefaultContext()
        );

        static::assertArrayHasKey($firstFileName, $configurations);
        static::assertArrayHasKey($secondFileName, $configurations);
        static::assertSame($firstId, $configurations[$firstFileName]->getId());
        static::assertSame($secondId, $configurations[$secondFileName]->getId());
        static::assertFalse($configurations[$secondFileName]->isEnabled());
        static::assertArrayNotHasKey($otherFamilyFileName, $configurations);

        foreach ($configurations as $configuration) {
            static::assertSame('agentic', $configuration->getFileFamily());
        }
    }

    /**
     * @return EntityRepository<ChannelFileCollection>
     */
    private function getChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('channel_file.repository');
    }

    private function getConfigurationLoader(): ChannelFileConfigurationLoader
    {
        return static::getContainer()->get(ChannelFileConfigurationLoader::class);
    }
}
