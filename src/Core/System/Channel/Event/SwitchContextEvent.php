<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;

class SwitchContextEvent implements ContenaChannelEvent
{
    public const string CONSISTENT_CHECK = self::class . '.consistent_check';
    public const string DATABASE_CHECK = self::class . '.database_check';

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private readonly RequestDataBag $requestData,
        private readonly ChannelContext $channelContext,
        private readonly DataValidationDefinition $dataValidationDefinition,
        private array $parameters,
    ) {
    }

    public function getRequestData(): RequestDataBag
    {
        return $this->requestData;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getDataValidationDefinition(): DataValidationDefinition
    {
        return $this->dataValidationDefinition;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function addParameter(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function deleteParameter(string $key): void
    {
        unset($this->parameters[$key]);
    }
}
