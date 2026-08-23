<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class SitemapRouteTest extends TestCase
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

    public function testEmpty(): void
    {
        $this->browser->request('POST', '/channel-api/sitemap');

        static::assertNotFalse($this->browser->getResponse()->getContent());

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertCount(0, $response);
    }

    public function testSitemapListsEntries(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)->create('', $this->ids->get('channel'));

        $fs = static::getContainer()->get('contena.filesystem.sitemap');
        $fs->write('sitemap/channel-' . $context->getChannelId() . '-' . $context->getLanguageId() . '/test.xml', 'some content');

        $this->browser->request('POST', '/channel-api/sitemap');

        static::assertNotFalse($this->browser->getResponse()->getContent());

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response);
        static::assertSame('sitemap', $response[0]['apiAlias']);
        static::assertArrayHasKey('filename', $response[0]);
        static::assertArrayHasKey('created', $response[0]);
        static::assertNotEmpty($response[0]['filename']);
        static::assertNotEmpty($response[0]['created']);
    }
}
