<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the BlogCommentsWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.6.9.0
 *
 * @final
 */
class BlogCommentsWidgetLoadedHook extends Hook implements ChannelContextAware
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'blog-comments-widget-loaded';

    public function __construct(
        private readonly BlogCommentResult $comments,
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

    public function getComments(): BlogCommentResult
    {
        return $this->comments;
    }
}
