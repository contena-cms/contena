<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * @internal
 */
class SeoUrlPlaceholderHandlerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;

    use KernelTestBehaviour;
    use FrontendChannelTestHelper;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        /** @var Router|MockObject $router */
        $router = static::createStub(Router::class);
        $router->method('generate')
            ->willReturnCallback(static fn ($name, $params) => match ($name) {
                BlogPageSeoUrlRoute::ROUTE_NAME => '/detail/' . ($params['blogId'] ?? ''),
                'frontend.navigation.page' => '/navigation/' . ($params['navigationId'] ?? ''),
                default => '',
            });

        $this->seoUrlPlaceholderHandler = new SeoUrlPlaceholderHandler(
            static::getContainer()->get('request_stack'),
            $router,
            static::getContainer()->get(Connection::class)
        );
    }

    public function testGenerateReplace(): void
    {
        $blogId = Uuid::randomHex();
        $host = 'http://foo.text';
        $template = 'Test content with url %s.';

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test frontend');

        $generated = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);

        $content = \sprintf($template, $generated);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $channelContext);

        $expectedUrl = $host . '/detail/' . $blogId;
        $expected = \sprintf($template, $expectedUrl);
        static::assertSame($expected, $actual);
    }

    public function testGenerateReplaceWithPrefixPath(): void
    {
        $blogId = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test content with url %s.';

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test frontend');

        $generated = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);

        $content = \sprintf($template, $generated);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $channelContext);

        $expectedUrl = $host . '/detail/' . $blogId;
        $expected = \sprintf($template, $expectedUrl);
        static::assertSame($expected, $actual);
    }

    public function testTwoUrls(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test URL 1: %s and URL 2: %s';

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test frontend');

        $generated1 = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId1]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId2]);

        $content = \sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $channelContext);

        $expectedUrl1 = $host . '/detail/' . $blogId1;
        $expectedUrl2 = $host . '/detail/' . $blogId2;
        $expected = \sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }

    public function testTwoEqualUrls(): void
    {
        $blogId = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test URL 1: %s and URL 2: %s';

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test frontend');

        $generated1 = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);

        $content = \sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $channelContext);

        $expectedUrl = $host . '/detail/' . $blogId;
        $expected = \sprintf($template, $expectedUrl, $expectedUrl);
        static::assertSame($expected, $actual);
    }

    public function testTwoDifferentEntities(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $host = 'http://foo.text:8000/de';
        $template = 'Test URL 1: %s and URL 2: %s';

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test frontend');

        $generated1 = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate('frontend.navigation.page', ['navigationId' => $categoryId]);

        $content = \sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $channelContext);

        $expectedUrl1 = $host . '/detail/' . $blogId;
        $expectedUrl2 = $host . '/navigation/' . $categoryId;
        $expected = \sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }

    public function testSeoReplacementChannelDefaultAndOverride(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test frontend');

        $seoUrls = [
            [
                'languageId' => $languageId,
                'channelId' => $channelId,
                'foreignKey' => $blogId,
                'routeName' => 'test',
                'pathInfo' => '/detail/' . $blogId,
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => true,
            ],
            [
                'languageId' => $languageId,
                'channelId' => null,
                'foreignKey' => $blogId,
                'routeName' => 'test',
                'pathInfo' => '/detail/' . $blogId,
                'seoPathInfo' => 'blog-default',
                'isCanonical' => true,
            ],
            [
                'languageId' => $languageId,
                'channelId' => null,
                'foreignKey' => $categoryId,
                'routeName' => 'test',
                'pathInfo' => '/navigation/' . $categoryId,
                'seoPathInfo' => 'cars-default',
                'isCanonical' => true,
            ],
        ];

        /** @var EntityRepository<SeoUrlCollection> $repo */
        $repo = static::getContainer()->get('seo_url.repository');
        $repo->create($seoUrls, Context::createDefaultContext());

        $host = 'http://foo.text:8000/de';
        $template = 'SEO 1: %s and SEO 2: %s';

        $generated1 = $this->seoUrlPlaceholderHandler->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);
        $generated2 = $this->seoUrlPlaceholderHandler->generate('frontend.navigation.page', ['navigationId' => $categoryId]);

        $content = \sprintf($template, $generated1, $generated2);
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $channelContext);

        $expectedUrl1 = $host . '/awesome-blog';
        $expectedUrl2 = $host . '/cars-default';
        $expected = \sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }
}
