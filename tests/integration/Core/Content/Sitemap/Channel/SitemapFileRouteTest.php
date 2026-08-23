<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Channel;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Service\SitemapLister;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class SitemapFileRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
    }

    public function testSitemapFiles(): void
    {
        $fileSystem = static::getContainer()->get('contena.filesystem.sitemap');
        static::assertInstanceOf(FilesystemOperator::class, $fileSystem);

        $sitemapLister = static::getContainer()->get(SitemapLister::class);
        static::assertInstanceOf(SitemapLister::class, $sitemapLister);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create('', $this->ids->get('channel'));

        $sitemapPath = 'sitemap/channel-' . $context->getChannelId() . '-' . $context->getLanguageId();

        $fileSystem->write($sitemapPath . '/test.xml.gz', 'bar');

        $sitemaps = $sitemapLister->getSitemaps($context);

        $filePath = $this->getSitemapFilePathFromUrl($sitemaps[0]->getFilename());

        $this->browser->request('POST', '/channel-api/sitemap/' . $filePath);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('bar', $this->browser->getInternalResponse()->getContent());
    }

    private function getSitemapFilePathFromUrl(string $url): string
    {
        $regex = '/sitemap\/([A-Za-z0-9-\/.]+)/';

        $matches = [];
        preg_match($regex, $url, $matches);

        $filePath = $matches[1] ?? null;
        static::assertIsString($filePath);

        return $filePath;
    }
}
