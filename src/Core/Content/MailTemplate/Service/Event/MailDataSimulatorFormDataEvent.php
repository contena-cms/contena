<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service\Event;

use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class MailDataSimulatorFormDataEvent extends Event
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly string $variableName,
        public readonly string $flowEventName,
        public readonly Context $context,
        private ?array $data = null,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}
