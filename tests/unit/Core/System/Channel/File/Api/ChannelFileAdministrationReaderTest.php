<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\File\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileEntity;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationConfiguration;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationDetail;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationListItem;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationReader;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationTemplate;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(ChannelFileAdministrationReader::class)]
class ChannelFileAdministrationReaderTest extends TestCase
{
    public function testListReturnsLightweightFileDescriptorsWithStoredConfiguration(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $file = $this->createChannelFile();
        $configuration = $this->createConfiguration($channelId, 'agentic', 'llms.txt');

        $discovery = $this->createMock(ChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('discover')
            ->with('agentic')
            ->willReturn(['llms.txt' => $file]);

        $configurationLoader = $this->createMock(ChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->once())
            ->method('loadForFileFamily')
            ->with('agentic', $channelId, $context)
            ->willReturn(['llms.txt' => $configuration]);

        $reader = new ChannelFileAdministrationReader(
            $discovery,
            $configurationLoader,
            $this->createTwigEnvironment(),
            $this->createTemplateResolver(),
        );

        static::assertEquals([
            new ChannelFileAdministrationListItem(
                'agentic',
                'llms.txt',
                'text/plain; charset=utf-8',
                new ChannelFileAdministrationConfiguration(
                    $configuration->getId(),
                    true,
                    [
                        'Framework' => 'Merchant override',
                    ],
                ),
            ),
        ], $reader->list('agentic', $channelId, $context));
    }

    public function testDetailReturnsTemplateSourcesAndContent(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $file = $this->createChannelFile();
        $configuration = $this->createConfiguration($channelId, 'agentic', 'llms.txt');

        $discovery = $this->createMock(ChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('discover')
            ->with('agentic')
            ->willReturn(['llms.txt' => $file]);

        $configurationLoader = $this->createMock(ChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->once())
            ->method('load')
            ->with('agentic', 'llms.txt', $channelId, $context)
            ->willReturn($configuration);

        $reader = new ChannelFileAdministrationReader(
            $discovery,
            $configurationLoader,
            $this->createTwigEnvironment(),
            $this->createTemplateResolver(),
        );

        static::assertEquals(new ChannelFileAdministrationDetail(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            [
                new ChannelFileAdministrationTemplate(
                    'Ucp',
                    '@Ucp/files/agentic/llms.txt.twig',
                    '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block user_provided_content %}{% endblock %}',
                    'extension',
                ),
                new ChannelFileAdministrationTemplate(
                    'Framework',
                    '@Framework/files/agentic/llms.txt.twig',
                    'Core template',
                    'base',
                ),
            ],
            true,
            new ChannelFileAdministrationConfiguration(
                $configuration->getId(),
                true,
                [
                    'Framework' => 'Merchant override',
                ],
            ),
        ), $reader->detail('agentic', 'llms.txt', $channelId, $context));
    }

    public function testDetailReturnsNullForUnknownFile(): void
    {
        $context = Context::createDefaultContext();

        $discovery = $this->createMock(ChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('discover')
            ->with('agentic')
            ->willReturn([]);

        $configurationLoader = $this->createMock(ChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->never())
            ->method('load');

        $reader = new ChannelFileAdministrationReader(
            $discovery,
            $configurationLoader,
            $this->createTwigEnvironment(),
            $this->createTemplateResolver(),
        );

        static::assertNull($reader->detail('agentic', 'missing.txt', Uuid::randomHex(), $context));
    }

    private function createChannelFile(): ChannelFile
    {
        return new ChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
        );
    }

    private function createConfiguration(string $channelId, string $fileFamily, string $fileName): ChannelFileEntity
    {
        $configuration = new ChannelFileEntity();
        $configuration->setId(Uuid::randomHex());
        $configuration->setChannelId($channelId);
        $configuration->setFileFamily($fileFamily);
        $configuration->setFileName($fileName);
        $configuration->setEnabled(true);
        $configuration->setTemplateOverrides(['Framework' => 'Merchant override']);

        return $configuration;
    }

    private function createTwigEnvironment(): Environment
    {
        return new Environment(new ArrayLoader([
            '@Ucp/files/agentic/llms.txt.twig' => '{% sw_extends \'files/agentic/llms.txt.twig\' %}{% block user_provided_content %}{% endblock %}',
            '@Framework/files/agentic/llms.txt.twig' => 'Core template',
        ]));
    }

    private function createTemplateResolver(): ChannelFileTemplateResolver
    {
        $resolver = static::createStub(ChannelFileTemplateResolver::class);
        $resolver
            ->method('resolveTemplateChain')
            ->willReturn([
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ]);

        return $resolver;
    }
}
