<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Framework\App\Subscriber;

use Contena\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Contena\Administration\Snippet\AppAdministrationSnippetCollection;
use Contena\Administration\Snippet\AppAdministrationSnippetEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Locale\LocaleException;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SystemLanguageChangedSubscriber::class)]
class SystemLanguageChangedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [SystemLanguageChangeEvent::class => 'onSystemLanguageChanged'],
            SystemLanguageChangedSubscriber::getSubscribedEvents()
        );
    }

    public function testDoesNotRunIfNoSnippetsExist(): void
    {
        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection()]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository = $this->createMock(EntityRepository::class),
            $snippetRepository
        );

        $localeRepository->expects($this->never())
            ->method('search');

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'en-GB',
            'en-US',
        ));
    }

    public function testOnSystemLanguageChangedThrowsExceptionWhenNewLocaleDoesNotExist(): void
    {
        $localeRepository = static::createStub(EntityRepository::class);
        $snippetRepository = static::createStub(EntityRepository::class);

        $snippetCollection = new AppAdministrationSnippetCollection([
            new AppAdministrationSnippetEntity()->assign([
                'id' => 'snippet-id',
                'appId' => 'app-id',
            ]),
        ]);

        $snippetSearchResult = static::createStub(EntitySearchResult::class);
        $snippetSearchResult->method('getEntities')->willReturn($snippetCollection);

        $snippetRepository->method('search')->willReturn($snippetSearchResult);

        $localeSearchResult = static::createStub(EntitySearchResult::class);
        $localeSearchResult->method('getEntities')->willReturn(new LocaleCollection());

        $localeRepository->method('search')->willReturn($localeSearchResult);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $this->expectExceptionObject(LocaleException::localeDoesNotExists('fr-DE'));

        $subscriber->onSystemLanguageChanged(
            new SystemLanguageChangeEvent(
                'previous-language-id',
                'fr-DE',
                'de-DE'
            )
        );
    }

    public function testDoesNotUpdateSnippetsIfSystemLanguageIsChangedFromEnGbToDeDe(): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale('de-DE')]),
        ]);

        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $this->createSnippet('app-one-id', $previousLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $this->createSnippet('app-two-id', $previousLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertEmpty($snippetRepository->creates);
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsForPreviousLocaleWithPreviousLocaleId(string $locale): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale($locale)]),
        ]);

        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $snippetOneToUpdate = $this->createSnippet('app-one-id', $newLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $snippetTwoToUpdate = $this->createSnippet('app-two-id', $newLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertSame([
            'id' => $snippetOneToUpdate->getId(),
            'localeId' => $previousLocale->getId(),
        ], $snippetRepository->updates[0][0]);

        static::assertSame([
            'id' => $snippetTwoToUpdate->getId(),
            'localeId' => $previousLocale->getId(),
        ], $snippetRepository->updates[1][0]);
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsForNewLocaleWithNewLocaleId(string $locale): void
    {
        /** @var StaticEntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale($locale)]),
        ]);

        /** @var StaticEntityRepository<AppAdministrationSnippetCollection> $snippetRepository */
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $this->createSnippet('app-one-id', $previousLocale->getId()),
            $this->createSnippet('app-two-id', $previousLocale->getId()),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertCount(2, $snippetRepository->updates);
    }

    public static function localeCodes(): \Generator
    {
        yield ['en-US'];
        yield ['it-IT'];
        yield ['es-ES'];
        yield ['fr-FR'];
    }

    private function createLocale(string $code): LocaleEntity
    {
        return new LocaleEntity()->assign([
            'id' => Uuid::randomHex(),
            'code' => $code,
        ]);
    }

    private function createSnippet(string $appId, string $localeId): AppAdministrationSnippetEntity
    {
        return new AppAdministrationSnippetEntity()->assign([
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'localeId' => $localeId,
            'value' => 'snippet-value',
        ]);
    }
}
