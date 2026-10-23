<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

class FlowLogEvent extends Event implements FlowEventAware
{
    final public const NAME = 'flow.log';

    /**
     * @var array<string, mixed>
     */
    private readonly array $config;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(
        private readonly string $name,
        private readonly FlowEventAware $event,
        ?array $config = []
    ) {
        $this->config = $config ?? [];
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvent(): FlowEventAware
    {
        return $this->event;
    }

    public function getContext(): Context
    {
        return $this->event->getContext();
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
