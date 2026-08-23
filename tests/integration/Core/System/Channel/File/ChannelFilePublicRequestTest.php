<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\File;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ChannelFilePublicRequestTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    public function testEnabledChannelFileIsServedThroughNotFoundFallback(): void
    {
        $channelId = $this->getChannelId();
        static::assertNotEmpty($channelId);

        $this->getChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $channelId,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'user_provided_content' => 'Custom public guidance',
                ],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', 'llms.txt', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        static::assertIsString($content);
        static::assertStringContainsString('This is a Contena-powered channel.', $content);
        static::assertStringContainsString('## Public resources', $content);
        static::assertStringContainsString('Custom public guidance', $content);
    }

    public function testAgentFileLookupPreservesLowercaseConfigurationAndIgnoresCase(): void
    {
        $channelId = $this->getChannelId();
        static::assertNotEmpty($channelId);

        $this->getChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'channelId' => $channelId,
                'fileFamily' => 'agentic',
                'fileName' => 'agents.md',
                'enabled' => true,
                'templateOverrides' => [
                    'user_provided_content' => 'Legacy custom guidance',
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
        static::assertNotEmpty($channelId);

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

    /**
     * @return EntityRepository<ChannelFileCollection>
     */
    private function getChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('channel_file.repository');
    }
}
