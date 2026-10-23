<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the BlogReviewsWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.6.9.0
 *
 * @final
 */
class BlogReviewsWidgetLoadedHook extends Hook implements ChannelContextAware
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'blog-reviews-widget-loaded';

    public function __construct(
        private readonly BlogReviewResult $reviews,
        ChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->channelContext = $context;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            ChannelRepositoryFacadeHookFactory::class,
        ];
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getReviews(): BlogReviewResult
    {
        return $this->reviews;
    }
}
