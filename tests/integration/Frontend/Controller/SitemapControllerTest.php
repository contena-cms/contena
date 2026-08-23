<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class SitemapControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    public function testSitemapXmlRendersSitemapIndex(): void
    {
        $response = $this->request('GET', 'sitemap.xml', []);
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame('text/xml; charset=utf-8', $response->headers->get('content-type'));
        static::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        static::assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
    }

    public function testSitemapProxyStreamsExistingFile(): void
    {
        $channelId = $this->getChannelId();
        $context = static::getContainer()->get(ChannelContextFactory::class)->create('', $channelId);
        $filePath = 'channel-' . $context->getChannelId() . '-' . $context->getLanguageId() . '/test.xml.gz';

        $fileSystem = static::getContainer()->get('contena.filesystem.sitemap');
        static::assertInstanceOf(FilesystemOperator::class, $fileSystem);
        $fileSystem->write('sitemap/' . $filePath, 'sitemap content');

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/sitemap/' . $filePath);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('sitemap content', $browser->getInternalResponse()->getContent());
        static::assertSame('application/octet-stream', $response->headers->get('content-type'));
        static::assertSame('15', $response->headers->get('content-length'));
    }

    public function testSitemapProxyReturnsNoContentForUnknownFile(): void
    {
        $channelId = $this->getChannelId();
        $context = static::getContainer()->get(ChannelContextFactory::class)->create('', $channelId);
        $filePath = 'channel-' . $context->getChannelId() . '-' . $context->getLanguageId() . '/missing.xml.gz';

        $response = $this->request('GET', 'sitemap/' . $filePath, []);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
