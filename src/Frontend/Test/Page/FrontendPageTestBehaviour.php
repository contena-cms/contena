<?php declare(strict_types=1);

namespace Contena\Frontend\Test\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Frontend\Page\PageLoadedEvent;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
trait FrontendPageTestBehaviour
{
    /**
     * @template TEvent of PageLoadedEvent
     *
     * @param class-string<TEvent> $expectedClass
     * @param TEvent|null $event
     */
    public static function assertPageEvent(
        string $expectedClass,
        ?PageLoadedEvent $event,
        ChannelContext $channelContext,
        Request $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($channelContext, $event->getChannelContext());
        TestCase::assertSame($channelContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPage());
    }

    /**
     * @template TEvent of PageletLoadedEvent
     *
     * @param class-string<TEvent> $expectedClass
     * @param TEvent $event
     */
    public static function assertPageletEvent(
        string $expectedClass,
        PageletLoadedEvent $event,
        ChannelContext $channelContext,
        Request $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($channelContext, $event->getChannelContext());
        TestCase::assertSame($channelContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPagelet());
    }

    abstract protected function getPageLoader();

    protected function expectParamMissingException(string $paramName): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter($paramName));
    }

    /**
     * @param array<string, mixed>|null $channelData
     */
    protected function createChannelContext(?array $channelData = null): ChannelContext
    {
        /** @var EntityRepository<MemberGroupCollection> $memberGroupRepository */
        $memberGroupRepository = static::getContainer()->get('member_group.repository');
        $memberGroupId = $memberGroupRepository->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        \assert($memberGroupId !== null);

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $navigationCategoryId = Uuid::randomHex();
        $categoryRepository->create([[
            'id' => $navigationCategoryId,
            'name' => 'Frontend navigation',
        ]], Context::createDefaultContext());

        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        \assert($snippetSetId !== null);

        $countryId = $this->getValidCountryId();
        $data = [
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'name' => 'frontend',
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'countryId' => $countryId,
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $navigationCategoryId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'countries' => [['id' => $countryId]],
            'domains' => [[
                'url' => 'http://test.com/' . Uuid::randomHex(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $snippetSetId,
            ]],
        ];

        if ($channelData !== null) {
            $data = array_merge($data, $channelData);
        }

        return $this->createContext($data);
    }

    /**
     * @template TEventName of Event
     *
     * @param class-string<TEventName> $eventName
     * @param TEventName|null $eventResult
     */
    protected function catchEvent(string $eventName, ?Event &$eventResult): void
    {
        $this->addEventListener(static::getContainer()->get('event_dispatcher'), $eventName, static function (Event $event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }

    abstract protected static function getContainer(): ContainerInterface;

    /**
     * @param array<string, mixed> $channel
     */
    private function createContext(array $channel): ChannelContext
    {
        $factory = static::getContainer()->get(ChannelContextFactory::class);
        $channelRepository = static::getContainer()->get('channel.repository');

        $channelId = Uuid::randomHex();
        $channel['id'] = $channelId;

        $channelRepository->create([$channel], Context::createDefaultContext());

        return $factory->create(Uuid::randomHex(), $channelId);
    }
}
