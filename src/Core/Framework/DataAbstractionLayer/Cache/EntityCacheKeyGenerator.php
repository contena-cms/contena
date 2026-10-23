<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Cache;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\ChannelContext;

class EntityCacheKeyGenerator
{
    public static function buildCmsTag(string $id): string
    {
        return 'cms-page-' . $id;
    }

    public static function buildBlogTag(string $id): string
    {
        return 'blog-' . $id;
    }

    public static function buildStreamTag(string $id): string
    {
        return 'blog-stream-' . $id;
    }

    /**
     * @param string[] $areas
     */
    public function getChannelContextHash(ChannelContext $context, array $areas = []): string
    {
        $ruleIds = $context->getRuleIdsByAreas($areas);

        return Hasher::hash([
            $context->getChannelId(),
            $context->getDomainId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyId(),
            $context->getTaxState(),
            $context->getItemRounding(),
            $ruleIds,
        ]);
    }

    public function getCriteriaHash(Criteria $criteria): string
    {
        return Hasher::hash([
            $criteria->getIds(),
            $criteria->getFilters(),
            $criteria->getTerm(),
            $criteria->getPostFilters(),
            $criteria->getQueries(),
            $criteria->getSorting(),
            $criteria->getLimit(),
            $criteria->getOffset() ?? 0,
            $criteria->getTotalCountMode(),
            $criteria->getGroupFields(),
            $criteria->getAggregations(),
            $criteria->getAssociations(),
            $criteria->getFields(),
            $criteria->getExcludedFields(),
        ]);
    }
}
