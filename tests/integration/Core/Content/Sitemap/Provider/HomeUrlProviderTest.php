<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Provider\HomeUrlProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class HomeUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create('', TestDefaults::CHANNEL);
    }

    public function testGetHomeUrlChannelIsExistingTwoDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->languageRepository->search($criteria, $this->channelContext->getContext())
            ->getEntities();

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de');
        $domain->setHreflangUseOnlyLocale(false);
        $first = $languages->first();
        static::assertInstanceOf(LanguageEntity::class, $first);
        $domain->setLanguageId($first->getId());

        $domains = $this->channelContext->getChannel()->getDomains();
        static::assertInstanceOf(ChannelDomainCollection::class, $domains);
        $domains->add($domain);

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $last = $languages->last();
        static::assertInstanceOf(LanguageEntity::class, $last);
        $domain->setLanguageId($last->getId());

        $domains->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->channelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithChannelIsExistingOneDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->languageRepository->search($criteria, $this->channelContext->getContext())
            ->getEntities();

        $languageId = $this->channelContext->getLanguageId();
        $language = $languages->get($languageId);
        static::assertInstanceOf(LanguageEntity::class, $language);

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($language->getId());

        static::assertInstanceOf(ChannelDomainCollection::class, $this->channelContext->getChannel()->getDomains());
        $this->channelContext->getChannel()->getDomains()->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->channelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithChannelHaveNoDomain(): void
    {
        $results = new HomeUrlProvider()->getUrls($this->channelContext, 100);

        static::assertEmpty($results->getUrls()[0]->getLoc());
    }

    public function testProviderNameIsHome(): void
    {
        $homeUrlProvider = new HomeUrlProvider();

        static::assertSame('home', $homeUrlProvider->getName());
    }
}
