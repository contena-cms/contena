<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
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

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
        $this->pageLoader = new AccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->translator,
        );
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

        $pageLoader = new AccountProfilePageLoader($genericPageLoader, $this->eventDispatcher, $translator);
        $context = static::createStub(ChannelContext::class);
        $context->method('getMember')->willReturn(new MemberEntity());
        $page = $pageLoader->load(new Request(), $context);

        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testcontena', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());
        static::assertCount(1, $this->eventDispatcher->getEvents());
        static::assertInstanceOf(AccountProfilePageLoadedEvent::class, $this->eventDispatcher->getEvents()[0]);
    }

    public function testSetStandardMetaData(): void
    {
        $pageLoader = new TestAccountProfilePageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->translator,
        );
        $page = new AccountProfilePage();

        static::assertNull($page->getMetaInformation());
        $pageLoader->setMetaInformationAccess($page);
        static::assertInstanceOf(MetaInformation::class, $page->getMetaInformation());
    }

    public function testNoMemberException(): void
    {
        $this->expectExceptionObject(ChannelException::memberNotLoggedIn());

        $this->pageLoader->load(new Request(), static::createStub(ChannelContext::class));
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
