<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Framework\Adapter\Cache\ChannelApiRouteCacheKeyEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 *
 * @codeCoverageIgnore
 */
class CrossSellingRouteCacheKeyEvent extends ChannelApiRouteCacheKeyEvent
{
    public function __construct(
        protected string $blogId,
        array $parts,
        Request $request,
        ChannelContext $context,
        ?Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct($parts, $request, $context, $criteria);
    }

    public function getBlogId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->blogId;
    }
}
