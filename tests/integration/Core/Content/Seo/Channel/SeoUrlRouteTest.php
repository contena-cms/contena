<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\Channel;

use PHPUnit\Framework\Attributes\Group;
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
#[Group('channel-api')]
class SeoUrlRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->createData();
    }

    public function testRequest(): void
    {
        $this->browser->request(
            'POST',
            '/channel-api/seo-url',
            [
            ]
        );
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertSame('seo_url', $response['elements'][0]['apiAlias']);
        static::assertSame('foo', $response['elements'][0]['pathInfo']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/channel-api/seo-url',
            [
                'includes' => [
                    'seo_url' => [
                        'pathInfo',
                    ],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertSame('seo_url', $response['elements'][0]['apiAlias']);
        static::assertSame('foo', $response['elements'][0]['pathInfo']);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    public function testFilterMiss(): void
    {
        $this->browser->request(
            'POST',
            '/channel-api/seo-url',
            [
                'filter' => [
                    [
                        'type' => 'equals',
                        'field' => 'pathInfo',
                        'value' => 'miss-string',
                    ],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(0, $response['total']);
    }

    public function testFilter(): void
    {
        $this->browser->request(
            'POST',
            '/channel-api/seo-url',
            [
                'filter' => [
                    [
                        'type' => 'equals',
                        'field' => 'pathInfo',
                        'value' => 'foo',
                    ],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
    }

    private function createData(): void
    {
        $data = [
            'id' => $this->ids->create('category'),
            'active' => true,
            'name' => 'Test',
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $data = [
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
            'channelId' => $this->ids->get('channel'),
            'pathInfo' => 'foo',
            'seoPathInfo' => 'foo',
            'isCanonical' => true,
            'foreignKey' => $this->ids->get('category'),
        ];

        static::getContainer()->get('seo_url.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
