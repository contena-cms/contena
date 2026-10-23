<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Exception\UserNotLoggedInException;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Salutation\Channel\SalutationRoute;
use Contena\Core\System\Salutation\Channel\SalutationRouteResponse;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\Salutation\SalutationEntity;
use Contena\Core\System\Salutation\SalutationSorter;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Event\RouteRequest\SalutationRouteRequestEvent;
use Contena\Frontend\Page\Account\Profile\AccountProfilePage;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountProfilePageLoader::class)]
class AccountProfilePageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AccountProfilePageLoader $pageLoader;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private SalutationRoute&Stub $salutationRoute;

    private SalutationSorter&Stub $salutationSorter;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->salutationRoute = static::createStub(SalutationRoute::class);
        $this->salutationSorter = static::createStub(SalutationSorter::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);

        $this->pageLoader = new AccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator
        );
    }

    public function testLoad(): void
    {
        $salutation = new SalutationEntity();
        $salutation->setId(Uuid::randomHex());

        $salutation2Id = Uuid::randomHex();
        $salutation2 = new SalutationEntity();
        $salutation2->setId($salutation2Id);

        $salutations = new SalutationCollection([$salutation, $salutation2]);
        $salutationResponse = new SalutationRouteResponse(
            new EntitySearchResult(
                SalutationDefinition::ENTITY_NAME,
                2,
                $salutations,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $salutationsSorted = new SalutationCollection([$salutation2, $salutation]);

        $salutationRoute = $this->createMock(SalutationRoute::class);
        $salutationRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($salutationResponse);

        $salutationSorter = $this->createMock(SalutationSorter::class);
        $salutationSorter
            ->expects($this->once())
            ->method('sort')
            ->willReturn($salutationsSorted);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');
        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $pageLoader = new AccountProfilePageLoader(
            $genericPageLoader,
            $this->eventDispatcher,
            $salutationRoute,
            $salutationSorter,
            $translator
        );

        $channelContext = $this->getContextWithDummyUser();
        $page = $pageLoader->load(new Request(), $channelContext);

        static::assertSame($salutationsSorted, $page->getSalutations());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(2, $events);

        static::assertInstanceOf(AccountProfilePageLoadedEvent::class, $events[1]);
        static::assertInstanceOf(SalutationRouteRequestEvent::class, $events[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->salutationRoute,
            $this->salutationSorter,
            $this->translator,
        );

        $page = new AccountProfilePage();

        static::assertNull($page->getMetaInformation());

        $pageLoader->setMetaInformationAccess($page);

        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNoUserException(): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        static::expectException(UserNotLoggedInException::class);

        $this->pageLoader->load(new Request(), $channelContext);
    }

    private function getContextWithDummyUser(): ChannelContext
    {
        $user = new UserEntity();

        $context = static::createStub(ChannelContext::class);
        $context
            ->method('getUser')
            ->willReturn($user);

        return $context;
    }
}

/**
 * @internal
 */
class TestAccountProfilePageLoader extends AccountProfilePageLoader
{
    public function setMetaInformationAccess(AccountProfilePage $page): void
    {
        self::setMetaInformation($page);
    }
}
