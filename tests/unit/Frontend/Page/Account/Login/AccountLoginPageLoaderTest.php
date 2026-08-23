<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Login;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\Channel\CountryRouteResponse;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Page\Account\Login\AccountLoginPage;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoadedEvent;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountLoginPageLoader::class)]
class AccountLoginPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private CountryRoute&Stub $countryRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->countryRoute = static::createStub(CountryRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericLoader = static::createStub(GenericPageLoader::class);
    }

    public function testLoad(): void
    {
        $country = new CountryEntity();
        $country->assign(['id' => Uuid::randomHex(), 'name' => 'lalaland']);
        $country->setUniqueIdentifier(Uuid::randomHex());
        $countries = new CountryCollection([$country]);
        $countryResponse = new CountryRouteResponse(new EntitySearchResult(
            1,
            $countries,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $countryRoute = $this->createMock(CountryRoute::class);
        $countryRoute->expects($this->once())->method('load')->willReturn($countryResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testcontena');

        $genericLoader = $this->createMock(GenericPageLoader::class);
        $genericLoader->expects($this->once())->method('load')->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('trans')->willReturn('translated');

        $pageLoader = new AccountLoginPageLoader($genericLoader, $this->eventDispatcher, $countryRoute, $translator);
        $page = $pageLoader->load(new Request(), static::createStub(ChannelContext::class));

        static::assertSame($countries, $page->getCountries());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testcontena', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());
        static::assertCount(1, $this->eventDispatcher->getEvents());
        static::assertInstanceOf(AccountLoginPageLoadedEvent::class, $this->eventDispatcher->getEvents()[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountLoginPageLoader(
            $this->genericLoader,
            $this->eventDispatcher,
            $this->countryRoute,
            $this->translator,
        );
        $page = new AccountLoginPage();

        static::assertNull($page->getMetaInformation());
        $pageLoader->setMetaInformationAccess($page);
        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }
}

/**
 * @internal
 */
class TestAccountLoginPageLoader extends AccountLoginPageLoader
{
    public function setMetaInformationAccess(AccountLoginPage $page): void
    {
        self::setMetaInformation($page);
    }
}
