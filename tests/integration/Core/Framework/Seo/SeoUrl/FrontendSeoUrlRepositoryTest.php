<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;

/**
 * @internal
 */
class FrontendSeoUrlRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendChannelTestHelper;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    /**
     * @var ChannelRepository<SeoUrlCollection>
     */
    private ChannelRepository $channelSeoUrlRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->channelSeoUrlRepository = static::getContainer()->get('channel.seo_url.repository');
    }

    public function testOnlyCanonical(): void
    {
        $canonicalId = Uuid::randomHex();
        $oldId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $canonicalId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/canonical',
                    'isCanonical' => true,
                ],
                [
                    'id' => $oldId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/old',
                    'isCanonical' => false,
                ],
            ],
            $channelContext->getContext()
        );

        $criteria = new Criteria([$canonicalId, $oldId]);

        $seoUrls = $this->channelSeoUrlRepository->search($criteria, $channelContext)->getEntities();
        static::assertCount(1, $seoUrls);
        $first = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($canonicalId, $first->getId());
    }

    public function testContextLanguage(): void
    {
        $deLanguageId = Uuid::randomHex();
        $deId = Uuid::randomHex();
        $enId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $this->upsertLanguage($deLanguageId, 'de-DE-1');
        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test', $deLanguageId);

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $deId,
                    'languageId' => $deLanguageId,
                    'channelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/de',
                    'isCanonical' => true,
                ],
                [
                    'id' => $enId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/en',
                    'isCanonical' => true,
                ],
            ],
            $channelContext->getContext()
        );

        $criteria = new Criteria([$deId, $enId]);

        $seoUrls = $this->channelSeoUrlRepository->search($criteria, $channelContext)->getEntities();
        static::assertCount(1, $seoUrls);
        $first = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($deId, $first->getId());
    }

    public function testContextChannel(): void
    {
        $expectedId = Uuid::randomHex();
        $otherId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $otherChannelId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $channelContext = $this->createFrontendChannelContext($channelId, 'test');
        $this->createFrontendChannelContext($otherChannelId, 'other');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $expectedId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => $channelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $otherId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => $otherChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/other',
                    'isCanonical' => true,
                ],
            ],
            $channelContext->getContext()
        );

        $criteria = new Criteria([$expectedId, $otherId]);

        $seoUrls = $this->channelSeoUrlRepository->search($criteria, $channelContext)->getEntities();
        static::assertCount(1, $seoUrls);
        $first = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($expectedId, $first->getId());
    }

    public function testChannelFallback(): void
    {
        $expectedId = Uuid::randomHex();
        $otherId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $otherChannelId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $channelContext = $this->createFrontendChannelContext($channelId, 'test');
        $this->createFrontendChannelContext($otherChannelId, 'other');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $expectedId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => null,
                    'routeName' => 'fallback',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $otherId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => $otherChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/other',
                    'isCanonical' => true,
                ],
            ],
            $channelContext->getContext()
        );

        $criteria = new Criteria([$expectedId, $otherId]);

        $seoUrls = $this->channelSeoUrlRepository->search($criteria, $channelContext)->getEntities();
        static::assertCount(1, $seoUrls);
        $first = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($expectedId, $first->getId());
    }

    public function testMatchingAndFallbackChannel(): void
    {
        $expectedId = Uuid::randomHex();
        $expectedFallbackId = Uuid::randomHex();
        $otherId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $otherChannelId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $channelContext = $this->createFrontendChannelContext($channelId, 'test');
        $this->createFrontendChannelContext($otherChannelId, 'other');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $expectedFallbackId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => null,
                    'routeName' => 'fallback',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $expectedId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => $channelId,
                    'routeName' => 'fallback',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $otherId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'channelId' => $otherChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/other',
                    'isCanonical' => true,
                ],
            ],
            $channelContext->getContext()
        );

        $criteria = new Criteria([$expectedId, $expectedFallbackId, $otherId]);

        $seoUrls = $this->channelSeoUrlRepository->search($criteria, $channelContext)->getEntities();
        static::assertCount(2, $seoUrls);

        $expected = $seoUrls->get($expectedId);
        static::assertInstanceOf(SeoUrlEntity::class, $expected);
        static::assertSame($channelId, $expected->getChannelId());

        $expectedFallback = $seoUrls->get($expectedFallbackId);
        static::assertInstanceOf(SeoUrlEntity::class, $expectedFallback);
        static::assertNull($expectedFallback->getChannelId());
    }

    private function upsertLanguage(string $id, string $name): void
    {
        $languageRepo = static::getContainer()->get('language.repository');
        $languageRepo->upsert([[
            'id' => $id,
            'name' => $name,
            'active' => true,
            'locale' => [
                'id' => $id,
                'code' => $name,
                'name' => 'test',
                'territory' => $name . ' territory',
            ],
            'translationCodeId' => $id,
        ]], Context::createDefaultContext());
    }
}
