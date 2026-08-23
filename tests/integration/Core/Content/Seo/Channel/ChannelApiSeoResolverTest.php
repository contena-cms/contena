<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Test\TestNavigationSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ChannelApiSeoResolverTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);
    }

    public function testDisabledState(): void
    {
        $this->browser->request(
            'POST',
            '/channel-api/category/home',
            [
            ]
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['seoUrls']);
    }

    public function testEnabled(): void
    {
        $this->browser->setServerParameter('HTTP_ct-include-seo-urls', '1');

        $this->browser->request(
            'POST',
            '/channel-api/category/home',
            [],
            [],
            []
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertCount(1, $response['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $response['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $response['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $response['seoUrls'][0]['pathInfo']);
    }

    public function testEnabledNoAuthentication(): void
    {
        $this->browser->setServerParameter('HTTP_ct-include-seo-urls', '1');

        $this->browser->request('GET', '/channel-api/test/channel-api-seo-resolver/no-auth-required', ['channel-id' => $this->ids->get('channel')]);

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertNull($response['seoUrls']);
    }

    private function createData(): void
    {
        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'seoUrls' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'foo',
                    'isCanonical' => true,
                ],
            ],
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
