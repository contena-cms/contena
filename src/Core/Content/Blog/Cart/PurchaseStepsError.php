<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Framework\Log\Package;

class PurchaseStepsError extends Error
{
    public function __construct(
        protected readonly string $id,
        protected readonly string $name,
        protected readonly int $quantity
    ) {
        $this->message = \sprintf(
            'Your input quantity does not match with the setup of the %s. The quantity was changed to %d',
            $name,
            $quantity
        );

        parent::__construct($this->message);
    }

    public function getParameters(): array
    {
        return ['name' => $this->name, 'quantity' => $this->quantity];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'purchase-steps-quantity';
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }
}
