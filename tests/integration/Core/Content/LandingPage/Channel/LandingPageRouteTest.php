<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\LandingPage\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Group('store-api')]
class LandingPageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private const LANGUAGE_IDS = [
        'en' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        'de' => '0f4ac850f69643cfb03d8d6ea5dc2647',
    ];

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testGetLandingPage(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertSame('My landing page', $response['name']);
        static::assertArrayHasKey('cmsPage', $response);
        static::assertSame($this->ids->get('cms-page'), $response['cmsPage']['id']);
    }

    public function testPostLandingPage(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame($this->ids->get('landing-page'), $response['id']);
        static::assertArrayHasKey('cmsPage', $response);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('requestParameterProvider')]
    public function testCompressedCriteriaIsReadLikeTheRequestBody(array $parameters): void
    {
        $url = '/store-api/landing-page/' . $this->ids->get('landing-page');

        $this->browser->request('POST', $url);
        $withoutParameters = $this->decodeResponse();

        $this->browser->request('POST', $url, $parameters);
        $expected = $this->decodeResponse();
        static::assertNotSame($withoutParameters, $expected, 'The parameters have to change the response');

        $compressed = gzencode(json_encode($parameters, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed, 'Gzip compressing failed');

        $this->browser->request('GET', $url, ['_criteria' => Base64::urlEncode($compressed)]);

        static::assertSame($expected, $this->decodeResponse());
    }

    /**
     * @return iterable<string, array{parameters: array<string, mixed>}>
     */
    public static function requestParameterProvider(): iterable
    {
        yield 'included fields of the response' => [
            'parameters' => ['includes' => ['landing_page' => ['id', 'name']]],
        ];

        yield 'slots of the CMS page, no slot has the given id' => [
            'parameters' => ['slots' => '0198c5a1f2d97c3e9b4a6d5e7f801234'],
        ];
    }

    public function testLoadLandingPageCmsSlotConfigFromParentLanguageOverride(): void
    {
        $this->createLanguages();

        $slotId = $this->ids->create('translated-slot');
        $landingPageId = $this->ids->create('translated-landing-page');

        /** @var EntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = static::getContainer()->get('cms_page.repository');
        /** @var EntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = static::getContainer()->get('landing_page.repository');

        $cmsPageId = $this->ids->create('translated-cms-page');
        $cmsPageRepository->create([[
            'id' => $cmsPageId,
            'name' => 'translated landing page',
            'type' => 'landingpage',
            'sections' => [[
                'id' => $this->ids->create('translated-section'),
                'type' => 'default',
                'position' => 0,
                'blocks' => [[
                    'type' => 'text',
                    'position' => 0,
                    'slots' => [[
                        'id' => $slotId,
                        'type' => 'text',
                        'slot' => 'content',
                        'config' => [
                            'content' => [
                                'source' => 'static',
                                'value' => 'layout placeholder',
                            ],
                        ],
                    ]],
                ]],
            ]],
        ]], Context::createDefaultContext());

        $landingPageRepository->create([[
            'id' => $landingPageId,
            'name' => 'Translated landing page',
            'url' => 'translated-landing-page',
            'active' => true,
            'cmsPageId' => $cmsPageId,
            'channels' => [[
                'id' => $this->ids->get('sales-channel'),
            ]],
            'slotConfig' => [
                $slotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'default language override',
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'languageId' => self::LANGUAGE_IDS['de'],
            'languages' => [
                ['id' => self::LANGUAGE_IDS['en']],
                ['id' => self::LANGUAGE_IDS['de']],
            ],
            'domains' => [[
                'languageId' => self::LANGUAGE_IDS['de'],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost/de-test',
            ]],
        ]);

        $this->browser->request('GET', '/store-api/context');
        $contextResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextResponse['token'],
            $this->ids->get('sales-channel'),
            [ChannelContextService::LANGUAGE_ID => self::LANGUAGE_IDS['de']],
        );

        $response = static::getContainer()->get(LandingPageRoute::class)->load(
            $landingPageId,
            new Request(),
            $channelContext,
        );

        $slot = $response->getLandingPage()
            ->getCmsPage()?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first();

        static::assertSame(
            'default language override',
            $slot?->getConfig()['content']['value'] ?? null
        );
    }

    private function createData(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository<CmsPageCollection> $cmsPageRepository */
        $cmsPageRepository = static::getContainer()->get('cms_page.repository');
        /** @var EntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = static::getContainer()->get('landing_page.repository');

        $cmsPageId = $this->ids->create('cms-page');
        $cmsPageRepository->create([
            [
                'id' => $cmsPageId,
                'name' => 'test page',
                'type' => 'blog_list',
                'sections' => [
                    [
                        'id' => $this->ids->create('section'),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'blog-listing',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'type' => 'blog-listing',
                                        'slot' => 'content',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);

        $landingPageRepository->create([
            [
                'id' => $this->ids->create('landing-page'),
                'name' => 'My landing page',
                'url' => 'my-landing-page',
                'active' => true,
                'cmsPageId' => $cmsPageId,
                'channels' => [
                    [
                        'id' => $this->ids->get('sales-channel'),
                    ],
                ],
            ],
        ], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createLanguages(): void
    {
        static::getContainer()->get('language.repository')->upsert([
            [
                'id' => self::LANGUAGE_IDS['en'],
                'name' => 'English',
                'localeId' => $this->getLocaleId('en-GB'),
                'translationCodeId' => $this->getLocaleId('en-GB'),
            ],
            [
                'id' => self::LANGUAGE_IDS['de'],
                'name' => 'German',
                'localeId' => $this->getLocaleId('de-DE'),
                'translationCodeId' => $this->getLocaleId('de-DE'),
                'parentId' => self::LANGUAGE_IDS['en'],
            ],
        ], Context::createDefaultContext());
    }

    private function getLocaleId(string $code): string
    {
        $localeId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM locale WHERE code = :code',
            ['code' => $code],
        );

        static::assertIsString($localeId);
        static::assertTrue(Uuid::isValid($localeId));

        return $localeId;
    }
}
