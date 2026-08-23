<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Overview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractMemberRoute;
use Contena\Core\System\Member\Channel\MemberResponse;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPage;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountOverviewPageLoader::class)]
class AccountOverviewPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private AbstractMemberRoute&Stub $memberRoute;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
        $this->memberRoute = static::createStub(AbstractMemberRoute::class);
    }

    public function testLoad(): void
    {
        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testcontena');

        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader->expects($this->once())->method('load')->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('trans')->willReturn('translated');

        $loadedMember = new MemberEntity();
        $memberRoute = $this->createMock(AbstractMemberRoute::class);
        $memberRoute->expects($this->once())->method('load')->willReturn(new MemberResponse($loadedMember));

        $pageLoader = new AccountOverviewPageLoader(
            $genericPageLoader,
            $this->eventDispatcher,
            $memberRoute,
            $translator,
        );

        $page = $pageLoader->load(new Request(), static::createStub(ChannelContext::class), new MemberEntity());

        static::assertSame($loadedMember, $page->getMember());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testcontena', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());
        static::assertCount(1, $this->eventDispatcher->getEvents());
        static::assertInstanceOf(AccountOverviewPageLoadedEvent::class, $this->eventDispatcher->getEvents()[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountOverviewPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->memberRoute,
            $this->translator,
        );
        $page = new AccountOverviewPage();

        static::assertNull($page->getMetaInformation());
        $pageLoader->setMetaInformationAccess($page);
        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }
}

/**
 * @internal
 */
class TestAccountOverviewPageLoader extends AccountOverviewPageLoader
{
    public function setMetaInformationAccess(AccountOverviewPage $page): void
    {
        self::setMetaInformation($page);
    }
}
