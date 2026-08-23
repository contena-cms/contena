<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\AbstractSeoResolver;
use Contena\Core\Content\Seo\SeoResolver;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlRequestContext;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class SeoResolverTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    private AbstractSeoResolver $seoResolver;

    private string $deLanguageId;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->seoResolver = static::getContainer()->get(SeoResolver::class);

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `channel`');

        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    public function testResolveEmpty(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, ''));
        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, '/'));
        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, '//'));
        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveSeoPathPassthrough(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, '/foo/bar'));
        static::assertSame('/foo/bar', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, 'foo/bar'));
        static::assertSame('/foo/bar', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveSeoPath(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => false,
            ],
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-blog-v2',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $languageId = $context->getLanguageId();

        // pathInfo
        foreach (['detail/1234', '/detail/1234', 'detail/1234/', '/detail/1234/'] as $path) {
            $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $channelId, $path));
            static::assertSame('/detail/1234', $resolved->pathInfo);
            static::assertFalse($resolved->isCanonical);
            static::assertSame('/awesome-blog-v2', $resolved->canonicalPathInfo);
        }

        // old canonical
        foreach (['awesome-blog', '/awesome-blog', 'awesome-blog/', '/awesome-blog/'] as $path) {
            $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $channelId, $path));
            static::assertSame('/detail/1234', $resolved->pathInfo);
            static::assertFalse($resolved->isCanonical);
            static::assertSame('/awesome-blog-v2', $resolved->canonicalPathInfo);
        }

        // canonical
        foreach (['awesome-blog-v2', '/awesome-blog-v2', 'awesome-blog-v2/', '/awesome-blog-v2/'] as $path) {
            $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $channelId, $path));
            static::assertSame('/detail/1234', $resolved->pathInfo);
            static::assertTrue($resolved->isCanonical);
        }
    }

    public function testResolveSeoPathWithCanonicalIsNull(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => null,
            ],
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-blog/',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, '/awesome-blog/'));
        static::assertSame('/detail/1234', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveCanonMultiLang(): void
    {
        $channelDeId = Uuid::randomHex();
        $this->createFrontendChannelContext(
            $channelDeId,
            'de',
            $this->deLanguageId,
            [Defaults::LANGUAGE_SYSTEM, $this->deLanguageId]
        );

        $deId = Uuid::randomHex();
        $enId = Uuid::randomHex();

        $this->seoUrlRepository->create([
            [
                'id' => $deId,
                'channelId' => $channelDeId,
                'languageId' => $this->deLanguageId,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-blog-de',
                'isCanonical' => true,
            ],
            [
                'id' => $enId,
                'channelId' => $channelDeId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-blog-en',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($this->deLanguageId, $channelDeId, 'awesome-blog-de'));
        static::assertNotNull($actual->id);
        static::assertSame($deId, Uuid::fromBytesToHex($actual->id));

        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $channelDeId, 'awesome-blog-en'));
        static::assertNotNull($actual->id);
        static::assertSame($enId, Uuid::fromBytesToHex($actual->id));
    }

    public function testResolveSamePathForDifferentChannels(): void
    {
        $otherChannelId = Uuid::randomHex();
        $this->createFrontendChannelContext(
            $otherChannelId,
            'de',
            $this->deLanguageId,
            [Defaults::LANGUAGE_SYSTEM, $this->deLanguageId]
        );

        $defaultId = Uuid::randomHex();
        $otherId = Uuid::randomHex();

        $this->seoUrlRepository->create([
            [
                'id' => $defaultId,
                'channelId' => null, // default
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/default',
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => true,
            ],
            [
                'id' => $otherId,
                'channelId' => $otherChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/other',
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $unknownChannelId = Uuid::randomHex();
        // returns default for unknown channels
        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $unknownChannelId, 'awesome-blog'));
        static::assertSame('/detail/default', $actual->pathInfo);
        static::assertTrue($actual->isCanonical);

        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $otherChannelId, 'awesome-blog'));
        static::assertSame('/detail/other', $actual->pathInfo);
        static::assertTrue($actual->isCanonical);
    }

    public function testChannelSpecificSeoulWillBePrioritized(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => null, // default
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/default',
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => true,
            ],
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/channel',
                'seoPathInfo' => 'awesome-blog',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $channelResponse = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $channelId, 'awesome-blog'));
        static::assertSame('/channel', $channelResponse->pathInfo);

        $channelResponse = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, Uuid::randomHex(), 'awesome-blog'));
        static::assertSame('/default', $channelResponse->pathInfo);
    }

    public function testResolveSeoPathWithCanonicalContainingQueryString(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/12345',
                'seoPathInfo' => 'Main-blog/SWDEMO10001?test=123',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $channelId,
            'Main-blog/SWDEMO10001',
            'test=123',
        ));

        static::assertSame('/detail/12345', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithPercentEncodedCharacter(): void
    {
        // Valid percent-escapes (e.g. "café" slugified to "caf%C3%A9") are kept storable by the
        // SEO path validation (see fix/seo-url-percent-400-13796); the resolver must therefore be
        // able to look them up verbatim. getPathInfo() keeps the path percent-encoded, so the
        // stored seo_path_info is compared as-is.
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/cafe',
                'seoPathInfo' => 'caf%C3%A9',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $channelId,
            'caf%C3%A9',
        ));

        static::assertSame('/detail/cafe', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithPercentEncodedQueryValue(): void
    {
        // A query-bearing SEO URL whose value contains a valid percent-escape ("ref=a%20b") must
        // resolve without triggering a canonical redirect. The raw request query is matched verbatim
        // against the stored seo_path_info, so the escape round-trips correctly.
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/12345',
                'seoPathInfo' => 'Main-blog/SWDEMO10001?ref=a%20b',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $channelId,
            'Main-blog/SWDEMO10001',
            'ref=a%20b',
        ));

        static::assertSame('/detail/12345', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithDifferentQueryStringDoesNotMatchCanonicalQuery(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/12345',
                'seoPathInfo' => 'Main-blog/SWDEMO10001?test=123',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $channelId,
            'Main-blog/SWDEMO10001',
            'test=12334',
        ));

        static::assertSame('/Main-blog/SWDEMO10001', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithQueryStringPrefersExactAlternativeMatch(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/base',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
                'isCanonical' => true,
            ],
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/secondary-b',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65?test=5.2',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $channelId,
            'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
            'test=5.2',
        ));

        static::assertSame('/detail/secondary-b', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithDifferentQueryValueFallsBackToPlainCanonical(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/base',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
                'isCanonical' => true,
            ],
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/secondary-54',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65?test=5.4',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $channelId,
            'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
            'test=5.42',
        ));

        static::assertSame('/detail/base', $resolved->pathInfo);
        static::assertSame('Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65', $resolved->seoPathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveWithoutQueryPrefersPlainCanonicalOverQueryAlternative(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->seoUrlRepository->create([
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/query',
                'seoPathInfo' => 'Main-blog/SWDEMO10001?test=123',
                'isCanonical' => true,
            ],
            [
                'channelId' => $channelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/plain',
                'seoPathInfo' => 'Main-blog/SWDEMO10001',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $channelId, 'Main-blog/SWDEMO10001'));

        static::assertSame('/detail/plain', $resolved->pathInfo);
        static::assertSame('Main-blog/SWDEMO10001', $resolved->seoPathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }
}
