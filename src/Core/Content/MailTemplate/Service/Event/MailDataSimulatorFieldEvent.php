<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Symfony\Contracts\EventDispatcher\Event;

class MailDataSimulatorFieldEvent extends Event
{
    private mixed $value = null;

    private bool $hasValue = false;

    public function __construct(
        public readonly Field $field,
        public readonly Context $context,
    ) {
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->hasValue = true;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }
}
