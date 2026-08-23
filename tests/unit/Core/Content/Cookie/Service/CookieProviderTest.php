<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieEntryCollection;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(CookieProvider::class)]
class CookieProviderTest extends TestCase
{
    public function testGetCookieGroups(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $cookieGroups = new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        )
        ->getCookieGroups(new Request(), Generator::generateChannelContext());

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        $collectEvent = $events[0];
        static::assertInstanceOf(CookieGroupCollectEvent::class, $collectEvent);
        static::assertSame($cookieGroups, $collectEvent->cookieGroupCollection);

        static::assertCount(2, $cookieGroups);

        $requiredGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertInstanceOf(CookieGroup::class, $requiredGroup);
        static::assertNotNull($requiredGroup->getEntries());
        static::assertCount(4, $requiredGroup->getEntries());

        $sessionCookie = $requiredGroup->getEntries()->get('test-session-name-');
        static::assertNotNull($sessionCookie);

        $cookiePreferenceCookie = $requiredGroup->getEntries()->get('cookie-preference');
        static::assertNotNull($cookiePreferenceCookie);
        static::assertTrue($cookiePreferenceCookie->hidden);

        $comfortFeaturesGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        static::assertInstanceOf(CookieGroup::class, $comfortFeaturesGroup);
        static::assertNotNull($comfortFeaturesGroup->getEntries());
        static::assertCount(2, $comfortFeaturesGroup->getEntries());

        $youtubeCookie = $comfortFeaturesGroup->getEntries()->get('youtube-video');
        static::assertNotNull($youtubeCookie);

        $vimeoCookie = $comfortFeaturesGroup->getEntries()->get('vimeo-video');
        static::assertNotNull($vimeoCookie);
    }

    public function testGetCookieGroupsWithTranslation(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $cookieGroupEntry = new CookieEntry('test-cookie');
            $cookieGroupEntry->name = 'cookie.entry.test';
            $cookieGroupEntry->description = 'cookie.entry.test.description';

            $newGroup = new CookieGroup('cookie.group.test');
            $newGroup->description = 'cookie.group.test.description';
            $newGroup->setEntries(new CookieEntryCollection([$cookieGroupEntry]));
            $event->cookieGroupCollection->add($newGroup);
        });

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn ($key) => 'Translated: ' . $key);
        $cookieGroups = new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        )
        ->getCookieGroups(new Request(), Generator::generateChannelContext());

        static::assertCount(3, $cookieGroups);
        $group = $cookieGroups->get('cookie.group.test');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('Translated: cookie.group.test', $group->name);
        static::assertSame('Translated: cookie.group.test.description', $group->description);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        $entry = $entries->get('test-cookie');
        static::assertNotNull($entry);
        static::assertSame('Translated: cookie.entry.test', $entry->name);
        static::assertSame('Translated: cookie.entry.test.description', $entry->description);
        static::assertSame('test-cookie', $entry->cookie);
    }

    public function testNewCookieAddedViaEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $newGroup = new CookieGroup('test');
            $newGroup->setCookie('test-cookie');
            $event->cookieGroupCollection->add($newGroup);
        });

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $cookieGroups = new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        )
        ->getCookieGroups(new Request(), Generator::generateChannelContext());
        static::assertCount(3, $cookieGroups);

        $testGroup = $cookieGroups->get('test');
        static::assertInstanceOf(CookieGroup::class, $testGroup);
    }
}
