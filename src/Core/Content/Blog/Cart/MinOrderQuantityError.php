<?php
declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Framework\Log\Package;

class MinOrderQuantityError extends Error
{
    public function __construct(
        protected string $id,
        protected string $name,
        protected int $quantity
    ) {
        $this->message = \sprintf(
            'The quantity of blog %s did not meet the minimum order quantity threshold. The quantity has automatically been increased to %d',
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
        return 'min-order-quantity';
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
