<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Contena\Core\Framework\Script\Execution\FunctionHook;
use Contena\Core\Framework\Script\Execution\InterfaceHook;
use Contena\Core\Framework\Script\ScriptException;
use Contena\Core\System\Channel\ChannelContext;

/**
 * Triggered when the api endpoint /channel-api/script/{hook} is called. Used to execute your logic and provide a response to the request.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 *
 * @final
 */
class ChannelApiHook extends InterfaceHook implements ChannelContextAware
{
    use ScriptResponseAwareTrait;

    final public const HOOK_NAME = 'channel-api-{hook}';

    final public const FUNCTIONS = [
        ChannelApiCacheKeyHook::FUNCTION_NAME => ChannelApiCacheKeyHook::class,
        ChannelApiResponseHook::FUNCTION_NAME => ChannelApiResponseHook::class,
    ];

    public function __construct(
        private readonly string $script,
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

    public function getName(): string
    {
        return \str_replace(
            ['{hook}'],
            [$this->script],
            self::HOOK_NAME
        );
    }

    public function getFunction(string $name): FunctionHook
    {
        if (!\array_key_exists($name, self::FUNCTIONS)) {
            throw ScriptException::functionDoesNotExistInInterfaceHook(self::class, $name);
        }

        $functionHook = self::FUNCTIONS[$name];

        return new $functionHook($this->getName(), $this->request, $this->query, $this->channelContext);
    }
}
