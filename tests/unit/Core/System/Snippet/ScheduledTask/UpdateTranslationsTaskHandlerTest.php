<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;
use Contena\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Contena\Core\System\Snippet\Service\TranslationUpdater;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(UpdateTranslationsTaskHandler::class)]
class UpdateTranslationsTaskHandlerTest extends TestCase
{
    public function testRunUpdatesOnlyTheLocalesOfFlaggedLanguages(): void
    {
        $languages = new LanguageCollection([
            $this->language('fr-FR', 'id-1'),
            $this->language('de-DE', 'id-2'),
            $this->language('fr-FR', 'id-3'),
            $this->language(null, 'id-4'),
        ]);

        $languageRepository = StaticEntityRepository::of(LanguageCollection::class, [
            static function (Criteria $criteria) use ($languages): LanguageCollection {
                $filter = $criteria->getFilters()[0] ?? null;
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('translationAutoUpdate', $filter->getField());
                static::assertTrue($filter->getValue());

                return $languages;
            },
        ], new LanguageDefinition());

        $updater = $this->createMock(TranslationUpdater::class);
        $updater->expects($this->once())
            ->method('updateInstalled')
            ->with(static::isInstanceOf(Context::class), ['fr-FR', 'de-DE'])
            ->willReturn(new TranslationUpdateResult());

        $this->handler($updater, $languageRepository)->run();
    }

    public function testRunSkipsWhenNoLanguageIsFlagged(): void
    {
        $languageRepository = StaticEntityRepository::of(LanguageCollection::class, [
            static fn (): LanguageCollection => new LanguageCollection(),
        ], new LanguageDefinition());

        $updater = $this->createMock(TranslationUpdater::class);
        $updater->expects($this->never())->method('updateInstalled');

        $this->handler($updater, $languageRepository)->run();
    }

    private function language(?string $localeCode, string $id): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setId($id);
        $language->setUniqueIdentifier($id);

        if ($localeCode !== null) {
            $locale = new LocaleEntity();
            $locale->setId($localeCode);
            $locale->setUniqueIdentifier($localeCode);
            $locale->setCode($localeCode);
            $language->setLocale($locale);
        }

        return $language;
    }

    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    private function handler(TranslationUpdater&MockObject $updater, EntityRepository $languageRepository): UpdateTranslationsTaskHandler
    {
        return new UpdateTranslationsTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $updater,
            $languageRepository,
        );
    }
}
