<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Hook\Pricing;

use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when blog prices are calculated for the store
 *
 * @hook-use-case blog
 *
 * @since 6.5.1.0
 *
 * @final
 */
class BlogPricingHook extends Hook implements ChannelContextAware
{
    final public const HOOK_NAME = 'blog-pricing';

    /**
     * @param BlogProxy[] $blogs
     *
     * @internal
     */
    public function __construct(
        private readonly array $blogs,
        private readonly ChannelContext $channelContext
    ) {
        parent::__construct($this->channelContext->getContext());
    }

    /**
     * @return BlogProxy[]
     */
    public function getBlogs(): iterable
    {
        return $this->blogs;
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

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
