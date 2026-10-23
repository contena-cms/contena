<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Content\Flow\Dispatching\Aware\CustomAppAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class CustomAppEvent extends Event implements CustomAppAware, FlowEventAware
{
    /**
     * @param array<string, mixed>|null $appData
     */
    public function __construct(
        private readonly string $name,
        private readonly ?array $appData,
        private readonly Context $context
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAppData(): ?array
    {
        return $this->appData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
