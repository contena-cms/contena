<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Contena\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Contena\Core\Framework\Script\Execution\OptionalFunctionHook;
use Contena\Core\System\Channel\ChannelContext;

/**
 * Triggered when the api endpoint /channel-api/script/{hook} is called. Used to provide a cache-key based on the request.
 * Needs to be implemented when your channel-api route should be cached.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 *
 * @final
 */
class ChannelApiCacheKeyHook extends OptionalFunctionHook implements ChannelContextAware, StoppableHook
{
    use StoppableHookTrait;

    final public const FUNCTION_NAME = 'cache_key';

    private ?string $cacheKey = null;

    public function __construct(
        private readonly string $name,
        /**
         * @var array<string, mixed>
         */
        private readonly array $request,
        /**
         * @var array<string, mixed>
         */
        private readonly array $query,
        private readonly ChannelContext $channelContext
    ) {
        parent::__construct($channelContext->getContext());
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    public function setCacheKey(string $key): void
    {
        $this->cacheKey = $key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function getServiceIds(): array
    {
        // No service access allowed for generating the cache key
        return [];
    }

    public function getFunctionName(): string
    {
        return self::FUNCTION_NAME;
    }
}
