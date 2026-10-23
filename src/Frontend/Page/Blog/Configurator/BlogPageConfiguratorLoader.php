<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog\Configurator;

use Contena\Core\Content\Blog\Channel\Detail\AvailableCombinationResult;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

class BlogPageConfiguratorLoader extends BlogConfiguratorLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly BlogConfiguratorLoader $loader)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(ChannelBlogEntity $blog, ChannelContext $context): PropertyGroupCollection
    {
        return $this->loader->load($blog, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function loadFromCombinations(
        ChannelBlogEntity $blog,
        AvailableCombinationResult $combinations,
        ChannelContext $context
    ): PropertyGroupCollection {
        return $this->loader->loadFromCombinations($blog, $combinations, $context);
    }
}
