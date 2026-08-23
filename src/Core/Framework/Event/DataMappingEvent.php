<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

class DataMappingEvent extends Event implements ContenaEvent
{
    /**
     * @param array<string, mixed> $output
     */
    public function __construct(
        private readonly DataBag $input,
        private array $output,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getInput(): DataBag
    {
        return $this->input;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * @param array<string, mixed> $output
     */
    public function setOutput(array $output): void
    {
        $this->output = $output;
    }
}
