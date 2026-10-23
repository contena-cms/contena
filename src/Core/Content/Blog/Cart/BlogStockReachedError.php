<?php
declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Framework\Log\Package;

class BlogStockReachedError extends Error
{
    public function __construct(
        protected readonly string $id,
        protected readonly string $name,
        protected readonly int $quantity,
        protected bool $resolved = true
    ) {
        $this->message = \sprintf(
            'The blog %s is only available %d times',
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getMessageKey(): string
    {
        return 'blog-stock-reached';
    }

    public function getLevel(): int
    {
        return $this->resolved ? self::LEVEL_WARNING : self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function isPersistent(): bool
    {
        return $this->resolved;
    }
}
