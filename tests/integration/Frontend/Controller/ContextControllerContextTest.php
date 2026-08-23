<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Event\ChannelContextSwitchEvent;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ContextControllerContextTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private KernelBrowser $browser;

    private string $testBaseUrl;

    private string $defaultBaseUrl;

    private string $languageId;

    private Router $router;

    protected function setUp(): void
    {
        $this->router = static::getContainer()->get('router');

        $this->languageId = Uuid::randomHex();
        $localeId = Uuid::randomHex();

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $domainPath = '/context-' . Uuid::randomHex();
        $this->defaultBaseUrl = rtrim($appUrl, '/') . $domainPath;
        $this->testBaseUrl = $this->defaultBaseUrl . '/tst-TST';

        $domains = [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->defaultBaseUrl,
            ],
            [
                'language' => [
                    'id' => $this->languageId,
                    'name' => 'Test',
                    'active' => true,
                    'locale' => [
                        'id' => $localeId,
                        'name' => 'Test',
                        'code' => 'af-ZA-x-context-test',
                        'territory' => 'Test',
                    ],
                    'translationCodeId' => $localeId,
                ],
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->testBaseUrl,
            ],
        ];

        $this->browser = $this->createCustomChannelBrowser([
            'domains' => $domains,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->languageId]],
        ]);

        $this->assignNavigationLayout();
    }

    protected function tearDown(): void
    {
        $this->router->getContext()->setBaseUrl('');
    }

    public function testSwitchToUpperCasePath(): void
    {
        $this->browser->request('GET', $this->defaultBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->defaultBaseUrl . '/channel/language',
            ['languageId' => $this->languageId]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->testBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchFromUpperCasePath(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/channel/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchWithWrongRedirectTo(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/channel/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => 'frontend.homer.page']
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchWithBlogIdAndCorrectRedirectTo(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $blogId = Uuid::randomHex();

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/channel/language',
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'redirectTo' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'redirectParameters' => ['blogId' => $blogId],
            ]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/blog/' . $blogId, $response->headers->get('Location'));
    }

    public function testConfigure(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $contextSubscriber = new ContextControllerTestSubscriber();
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addSubscriber($contextSubscriber);

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/channel/configure',
            ['languageId' => $this->languageId],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        $response = $this->browser->getResponse();

        $dispatcher->removeSubscriber($contextSubscriber);

        static::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->languageId, $contextSubscriber->switchEvent->getRequestDataBag()->get('languageId'));
    }

    private function assignNavigationLayout(): void
    {
        $channelId = $this->browser->getServerParameter('test-channel-id');
        static::assertIsString($channelId);

        $channel = static::getContainer()->get('channel.repository')->search(
            new Criteria([$channelId]),
            Context::createDefaultContext(),
        )->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        $contentLayoutId = static::getContainer()->get('content_layout.repository')->searchIds(
            new Criteria()->addFilter(new EqualsFilter('rootSource', 'category'))->setLimit(1),
            Context::createDefaultContext(),
        )->firstId();
        static::assertNotNull($contentLayoutId);

        static::getContainer()->get('category_content_layout.repository')->create([[
            'id' => Uuid::randomHex(),
            'categoryId' => $channel->getNavigationCategoryId(),
            'channelId' => $channelId,
            'contentLayoutId' => $contentLayoutId,
        ]], Context::createDefaultContext());
    }
}

/**
 * @internal
 */
class ContextControllerTestSubscriber implements EventSubscriberInterface
{
    public ChannelContextSwitchEvent $switchEvent;

    public static function getSubscribedEvents(): array
    {
        return [
            ChannelContextSwitchEvent::class => 'onSwitch',
        ];
    }

    public function onSwitch(ChannelContextSwitchEvent $event): void
    {
        $this->switchEvent = $event;
    }
}
