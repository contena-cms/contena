<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\ScheduledTask;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Snippet\Service\TranslationUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: UpdateTranslationsTask::class)]
final class UpdateTranslationsTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly TranslationUpdater $translationUpdater,
        private readonly EntityRepository $languageRepository,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translationAutoUpdate', true));
        $criteria->addAssociation('locale');

        $languages = $this->languageRepository->search($criteria, $context)->getEntities();
        $locales = array_values(array_unique(array_filter(
            $languages->map(static fn (LanguageEntity $language): ?string => $language->getLocale()?->getCode())
        )));

        if ($locales === []) {
            return;
        }

        $this->translationUpdater->updateInstalled($context, $locales);
    }
}
