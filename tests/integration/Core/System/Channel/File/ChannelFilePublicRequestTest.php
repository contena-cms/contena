<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\File;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ChannelFilePublicRequestTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testEnabledChannelFileIsServedThroughNotFoundFallback(): void
    {
        $channelId = $this->getChannelId();
        static::assertNotSame('', $channelId);

        $this->getChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $channelId,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'admin_user_provided_content' => 'Custom public guidance',
                ],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', 'llms.txt', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        static::assertIsString($content);
        static::assertStringContainsString('This is a Contena-powered online shop.', $content);
        static::assertStringContainsString('## Public resources', $content);
        static::assertStringContainsString('Custom public guidance', $content);
    }

    public function testAgentFileLookupPreservesLowercaseConfigurationAndIgnoresCase(): void
    {
        $channelId = $this->getChannelId();
        static::assertNotSame('', $channelId);

        $this->getChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $channelId,
                'fileFamily' => 'agentic',
                'fileName' => 'agents.md',
                'enabled' => true,
                'templateOverrides' => [
                    'admin_user_provided_content' => 'Legacy custom guidance',
                ],
            ],
        ], Context::createDefaultContext());

        $uppercaseResponse = $this->request('GET', 'AGENTS.md', []);
        $lowercaseResponse = $this->request('GET', 'agents.md', []);

        static::assertSame(Response::HTTP_OK, $uppercaseResponse->getStatusCode());
        static::assertSame(Response::HTTP_OK, $lowercaseResponse->getStatusCode());
        static::assertSame($uppercaseResponse->getContent(), $lowercaseResponse->getContent());
        static::assertStringContainsString('Legacy custom guidance', (string) $uppercaseResponse->getContent());
    }

    public function testEnabledAiCatalogDoesNotExposeAdminMcpServer(): void
    {
        $channelId = $this->getChannelId();
        static::assertNotSame('', $channelId);

        $this->getChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $channelId,
                'fileFamily' => 'agentic',
                'fileName' => '.well-known/ai-catalog.json',
                'enabled' => true,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', '.well-known/ai-catalog.json', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertSame('application/json; charset=utf-8', $response->headers->get('content-type'));
        static::assertIsString($content);

        $catalog = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($catalog);
        static::assertSame('1.0', $catalog['specVersion']);
        static::assertSame([], $catalog['entries']);
        static::assertStringNotContainsString('/api/_mcp', $content);
    }

    public function testCachedDiscoveryUsesCurrentTwigLoaderForLaterAppTemplates(): void
    {
        $channelId = $this->getChannelId();
        static::assertNotSame('', $channelId);

        static::getContainer()->get('cache.object')->clear();
        static::getContainer()
            ->get(ChannelFileDiscovery::class)
            ->get('files/agentic/.well-known/ai-catalog.json.twig');

        $this->createAiCatalogAppTemplate();
        // The test mutates app templates after warming TemplateFinder in the same kernel.
        static::getContainer()->get(TemplateFinder::class)->reset();

        $this->getChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $channelId,
                'fileFamily' => 'agentic',
                'fileName' => '.well-known/ai-catalog.json',
                'enabled' => true,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', '.well-known/ai-catalog.json', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertIsString($content);

        $catalog = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($catalog);
        static::assertSame('urn:air:test:resource:app-template', $catalog['entries'][0]['identifier'] ?? null);
    }

    /**
     * @return EntityRepository<ChannelFileCollection>
     */
    private function getChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('channel_file.repository');
    }

    private function createAiCatalogAppTemplate(): void
    {
        static::getContainer()->get('app_template.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'path' => 'files/agentic/.well-known/ai-catalog.json.twig',
                'active' => true,
                'template' => <<<'TWIG'
                    {% sw_extends 'files/agentic/.well-known/ai-catalog.json.twig' %}

                    {% block agentic_ai_catalog_entries %}
                        {% set entries = entries|merge([{
                            identifier: 'urn:air:test:resource:app-template',
                            displayName: 'App template entry',
                            type: 'text/plain',
                            url: '/app-template-entry',
                        }]) %}

                        {{ parent() }}
                    {% endblock %}
                    TWIG,
                'app' => [
                    'name' => 'ChannelFileCacheApp',
                    'path' => __DIR__ . '/../../../Framework/App/Manifest/_fixtures/test',
                    'version' => '0.0.1',
                    'label' => 'Sales channel file cache app',
                    'accessToken' => 'sales-channel-file-cache-app',
                    'active' => true,
                    'integration' => [
                        'label' => 'Sales channel file cache app',
                        'accessKey' => 'sales-channel-file-cache-app',
                        'secretAccessKey' => 'sales-channel-file-cache-app',
                    ],
                    'aclRole' => [
                        'name' => 'ChannelFileCacheApp',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
