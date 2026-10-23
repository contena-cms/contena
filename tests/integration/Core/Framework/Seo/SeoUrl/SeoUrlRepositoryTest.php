<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Seo\SeoUrl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class SeoUrlRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
    }

    public function testCreate(): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => '/pretty/path',

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();
        $events = $this->seoUrlRepository->create([$url], $context);
        static::assertNotNull($events->getEvents());
        static::assertCount(1, $events->getEvents());

        $event = $events->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => '/pretty/path',

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();
        $this->seoUrlRepository->create([$url], $context);

        $update = [
            'id' => $id,
            'seoPathInfo' => '/even/prettier/path',
        ];
        $events = $this->seoUrlRepository->update([$update], $context);
        $event = $events->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());

        $first = $this->seoUrlRepository->search(new Criteria([$id]), $context)
            ->getEntities()
            ->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($update['id'], $first->getId());
        static::assertSame($update['seoPathInfo'], $first->getSeoPathInfo());
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => '/pretty/path',

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();
        $this->seoUrlRepository->create([$url], $context);

        $result = $this->seoUrlRepository->delete([['id' => $id]], $context);
        $event = $result->getEventByEntityName(SeoUrlDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertSame([$id], $event->getIds());

        $first = $this->seoUrlRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNull($first);
    }

    public function testEmptySeoUrlCollection(): void
    {
        /** @phpstan-ignore argument.type (Intentionally providing an empty generator for test purpose) */
        $registry = new SeoUrlRouteRegistry($this->emptyGenerator());
        static::assertSame([], (array) $registry->getSeoUrlRoutes());

        $registry = new SeoUrlRouteRegistry([]);
        static::assertSame([], (array) $registry->getSeoUrlRoutes());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidSeoPathInfoProvider(): iterable
    {
        yield 'percent (issue #13796)' => ['seo/url%/1'];
        yield 'fragment' => ['foo/bar#baz'];
        yield 'backslash' => ['foo\\bar'];
    }

    /**
     * Query strings and valid percent-escapes are URL-allowed and resolvable
     * by the SEO resolver, so writing them must stay possible.
     */
    public function testWritingUrlAllowedSeoPathInfoSucceeds(): void
    {
        $urls = array_map(static fn (string $seoPathInfo) => [
            'id' => Uuid::randomHex(),
            'channelId' => TestDefaults::CHANNEL,
            'foreignKey' => Uuid::randomHex(),

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => $seoPathInfo,

            'isCanonical' => true,
            'isModified' => false,
        ], ['foo/bar?x=1', 'caf%C3%A9/SW10098']);

        $written = $this->seoUrlRepository->create($urls, Context::createDefaultContext());

        static::assertCount(2, $written->getPrimaryKeys(SeoUrlDefinition::ENTITY_NAME));
    }

    #[DataProvider('invalidSeoPathInfoProvider')]
    public function testWritingInvalidSeoPathInfoIsRejected(string $invalidSeoPathInfo): void
    {
        $id = Uuid::randomHex();
        $fk = Uuid::randomHex();
        $url = [
            'id' => $id,
            'channelId' => TestDefaults::CHANNEL,
            'foreignKey' => $fk,

            'routeName' => 'testRoute',
            'pathInfo' => '/ugly/path',
            'seoPathInfo' => $invalidSeoPathInfo,

            'isCanonical' => true,
            'isModified' => false,
        ];

        $context = Context::createDefaultContext();

        try {
            $this->seoUrlRepository->create([$url], $context);
            static::fail(\sprintf('Expected WriteException for invalid SEO path "%s"', $invalidSeoPathInfo));
        } catch (WriteException $exception) {
            $violationException = $exception->getExceptions()[0] ?? null;
            static::assertInstanceOf(WriteConstraintViolationException::class, $violationException);

            $violation = $violationException->getViolations()->get(0);
            static::assertSame(ValidSeoPathInfo::INVALID_CHARACTERS, $violation->getCode());
            static::assertSame('/seoPathInfo', $violation->getPropertyPath());
        }
    }

    /**
     * @return \Generator<array{}>
     */
    private function emptyGenerator(): \Generator
    {
        yield from [];
    }
}
