<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Language\LanguageCollection;

/**
 * @internal
 */
class ChannelDomainGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function getDefinition(): string
    {
        return ChannelDomainDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $frontendChannelId = $this->getFrontendChannel($context);

        // Get the languages that are not the system language.
        $languages = $this->getNonSystemLanguages($context);

        if ($languages->count() === 0) {
            $context->getConsole()->note('Skipping channel_domain generation. No other language found.');

            return;
        }

        if (!$frontendChannelId) {
            $context->getConsole()->note('Skipping channel_domain generation. No frontend sales channel found.');

            return;
        }

        // If there is already more than one sales channel domain, do nothing.
        if ($this->getCurrentChannelDomains($context, $frontendChannelId)->count() > 1) {
            $context->getConsole()->note('Skipping channel_domain generation. Already exists.');

            return;
        }

        $context->getConsole()->progressStart($numberOfItems);

        $this->addLanguagesToChannel($context, $frontendChannelId, $languages->getIds());

        $appUrl = (string) (EnvironmentHelper::getVariable('APP_URL') ?? 'http://localhost:8000');
        $channelDomains = [];

        foreach ($languages as $language) {
            $locale = $language->getLocale();

            if ($locale === null) {
                continue;
            }

            $localeCode = $locale->getCode();
            $url = rtrim($appUrl, '/') . '/' . ltrim(strtolower($localeCode), '/');
            $snippetSetId = $this->getSnippetSetByIso($context, $localeCode);

            // If no matching snippet set is found, do not add the sales channel domain.
            if ($snippetSetId === null) {
                continue;
            }

            $channelDomains[] = [
                'id' => Uuid::randomHex(),
                'url' => $url,
                'channelId' => $frontendChannelId,
                'languageId' => $language->getId(),
                'snippetSetId' => $snippetSetId,
                'currencyId' => Defaults::CURRENCY,
            ];
        }

        $channelDomainRepository = $this->registry->getRepository('channel_domain');
        $channelDomainRepository->upsert($channelDomains, $context->getContext());

        $context->getConsole()->progressFinish();
    }

    /**
     * @param array<string> $languageIds
     */
    private function addLanguagesToChannel(DemodataContext $context, string $channelId, array $languageIds): void
    {
        $channelRepository = $this->registry->getRepository('channel');

        $channelRepository->update([
            [
                'id' => $channelId,
                'languages' => array_map(
                    static fn (string $languageId) => ['id' => $languageId],
                    $languageIds
                ),
            ],
        ], $context->getContext());
    }

    private function getFrontendChannel(DemodataContext $context): ?string
    {
        $channelRepository = $this->registry->getRepository('channel');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND));

        return $channelRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function getNonSystemLanguages(DemodataContext $context): LanguageCollection
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = $this->registry->getRepository('language');
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('id', Defaults::LANGUAGE_SYSTEM));
        $criteria->addAssociation('locale');

        return $languageRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function getSnippetSetByIso(DemodataContext $context, string $iso): ?string
    {
        $snippetSetRepository = $this->registry->getRepository('snippet_set');
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('iso', $iso));

        return $snippetSetRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function getCurrentChannelDomains(DemodataContext $context, string $frontendChannelId): ChannelDomainCollection
    {
        /** @var EntityRepository<ChannelDomainCollection> $channelDomainRepository */
        $channelDomainRepository = $this->registry->getRepository('channel_domain');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('channelId', $frontendChannelId));

        return $channelDomainRepository->search($criteria, $context->getContext())->getEntities();
    }
}
