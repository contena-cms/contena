<?php declare(strict_types=1);

namespace Contena\Administration\Framework\App\Subscriber;

use Contena\Administration\Snippet\AppAdministrationSnippetCollection;
use Contena\Administration\Snippet\AppAdministrationSnippetEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Locale\LocaleException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class SystemLanguageChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<LocaleCollection> $localeRepository
     * @param EntityRepository<AppAdministrationSnippetCollection> $snippetRepository
     */
    public function __construct(
        private EntityRepository $localeRepository,
        private EntityRepository $snippetRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [SystemLanguageChangeEvent::class => 'onSystemLanguageChanged'];
    }

    public function onSystemLanguageChanged(SystemLanguageChangeEvent $event): void
    {
        if ($event->previousLocaleCode === 'en-GB' && $event->newLocaleCode === 'de-DE') {
            return;
        }

        $context = Context::createDefaultContext();
        $snippets = $this->getSnippets($context);
        if ($snippets->count() === 0) {
            return;
        }

        $appsWithSnippets = array_values(array_unique($snippets->map(static fn (AppAdministrationSnippetEntity $snippet): string => $snippet->getAppId())));
        $previousLocale = $this->getLocale($event->previousLocaleCode, $context);
        $newLocale = $this->getLocale($event->newLocaleCode, $context);
        foreach ($appsWithSnippets as $appId) {
            $updates = [];
            $forPrevious = $this->snippetForLocale($snippets, $appId, $newLocale);
            if ($forPrevious) {
                $updates[] = ['id' => $forPrevious->getId(), 'localeId' => $previousLocale->getId()];
            }
            $forNew = $this->snippetForLocale($snippets, $appId, $previousLocale);
            if ($forNew) {
                $updates[] = ['id' => $forNew->getId(), 'localeId' => $newLocale->getId()];
            }
            $this->snippetRepository->update($updates, $context);
        }
    }

    private function getLocale(string $code, Context $context): LocaleEntity
    {
        $locale = $this->localeRepository->search(new Criteria()->addFilter(new EqualsFilter('code', $code)), $context)->getEntities()->first();
        if (!$locale instanceof LocaleEntity) {
            throw LocaleException::localeDoesNotExists($code);
        }

        return $locale;
    }

    private function getSnippets(Context $context): AppAdministrationSnippetCollection
    {
        return $this->snippetRepository->search(new Criteria(), $context)->getEntities();
    }

    private function snippetForLocale(AppAdministrationSnippetCollection $snippets, string $appId, LocaleEntity $locale): ?AppAdministrationSnippetEntity
    {
        return $snippets->filter(static fn (AppAdministrationSnippetEntity $snippet): bool => $appId === $snippet->getAppId() && $snippet->getLocaleId() === $locale->getId())->first();
    }
}
