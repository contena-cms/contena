<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Page\Navigation\Error\ErrorPageLoadedEvent;
use Contena\Frontend\Page\Navigation\Error\ErrorPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ErrorPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    private ChannelContext $channelContext;

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $cmsPageRepository;

    private string $errorLayoutId;

    protected function setUp(): void
    {
        parent::setUp();

        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $this->cmsPageRepository = static::getContainer()->get('cms_page.repository');
        $this->channelContext = $contextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $this->errorLayoutId = $this->createPage();
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.http404Page', $this->errorLayoutId);
    }

    public function testItDoesLoad404CmsLayoutPageIn404Case(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(ErrorPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($this->errorLayoutId, $request, $context);

        self::assertPageEvent(ErrorPageLoadedEvent::class, $event, $context, $request, $page);
        static::assertSame('404 layout', $page->getCmsPage()?->getName());
    }

    protected function getPageLoader(): ErrorPageLoader
    {
        return static::getContainer()->get(ErrorPageLoader::class);
    }

    private function createPage(): string
    {
        $page = [
            'id' => Uuid::randomHex(),
            'name' => '404 layout',
            'type' => 'page',
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [
                        [
                            'position' => 1,
                            'type' => 'image-text',
                            'slots' => [
                                ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => '404 - Not Found']]],
                                ['type' => 'image', 'slot' => 'right', 'config' => ['url' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'http://contena.cn/image.jpg']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], $this->channelContext->getContext());

        return $page['id'];
    }
}
