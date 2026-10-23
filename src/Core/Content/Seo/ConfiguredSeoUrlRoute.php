<?php

declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelEntity;

class ConfiguredSeoUrlRoute implements SeoUrlRouteInterface
{
    public function __construct(
        private readonly SeoUrlRouteInterface $decorated,
        private readonly SeoUrlRouteConfig $config
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return $this->config;
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $this->decorated->prepareCriteria($criteria, $channel);
    }

    public function getMapping(Entity $entity, ?ChannelEntity $channel): SeoUrlMapping
    {
        return $this->decorated->getMapping($entity, $channel);
    }
}
