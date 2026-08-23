<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Cache;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\ChannelContext;

class EntityCacheKeyGenerator
{
    public static function buildBlogTag(string $id): string
    {
        return 'blog-' . $id;
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
