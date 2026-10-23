<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Service;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationEntity;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;

readonly class EntityCmsSlotConfigInheritanceBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @template TTranslation of CategoryTranslationEntity|LandingPageTranslationEntity|BlogTranslationEntity
     *
     * @param EntityCollection<TTranslation>|null $translations
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function build(?EntityCollection $translations, ChannelContext $context): ?array
    {
        $slotConfigs = $this->collectSlotConfigs($translations);
        $languageInheritanceChain = $this->getLanguageInheritanceChain($context);

        /**
         * Merge field-by-field within each slot so that partial slot overrides in the
         * child language do not drop fields that are still inherited from the parent
         * language. Later entries in the chain (child) win over earlier ones (parent).
         */
        $merged = [];
        foreach ($languageInheritanceChain as $currentLanguageId) {
            foreach ($slotConfigs[$currentLanguageId] ?? [] as $slotId => $fields) {
                if ($fields) {
                    $merged[$slotId] = \array_replace($merged[$slotId] ?? [], $fields);
                }
            }
        }

        return $merged !== [] ? $merged : null;
    }

    /**
     * @template TTranslation of CategoryTranslationEntity|LandingPageTranslationEntity|BlogTranslationEntity
     *
     * @param EntityCollection<TTranslation>|null $translations
     *
     * @return array<string, array<string, array<string, array<string, mixed>>|null>>
     */
    private function collectSlotConfigs(?EntityCollection $translations): array
    {
        if ($translations === null) {
            return [];
        }

        $slotConfigs = [];
        foreach ($translations as $translation) {
            $slotConfig = $translation->getSlotConfig();
            if ($slotConfig === null) {
                continue;
            }

            $slotConfigs[$translation->getLanguageId()] = $slotConfig;
        }

        return $slotConfigs;
    }

    /**
     * @return non-empty-list<string>
     */
    private function getLanguageInheritanceChain(ChannelContext $context): array
    {
        $languageId = $context->getLanguageId();

        return [
            ...$this->getParentLanguageInheritanceChain($languageId),
            $languageId,
        ];
    }

    /**
     * @return list<string>
     */
    private function getParentLanguageInheritanceChain(string $languageId): array
    {
        $parentLanguageId = $this->getParentLanguageId($languageId);

        if ($parentLanguageId === null) {
            return [];
        }

        return [
            ...$this->getParentLanguageInheritanceChain($parentLanguageId),
            $parentLanguageId,
        ];
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        $parentId = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(language.parent_id))')
            ->from('language', 'language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->executeQuery()
            ->fetchOne();

        if ($parentId === false) {
            return null;
        }

        return $parentId;
    }
}
