<?php
declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Framework\Log\Package;

class BlogOutOfStockError extends Error
{
    protected string $name;

    public function __construct(
        protected string $id,
        string $name,
    ) {
        $this->message = \sprintf('The blog %s is no longer available', $name);

        parent::__construct($this->message);
        $this->name = $name;
    }

    public function getParameters(): array
    {
        return ['name' => $this->name];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMessageKey(): string
    {
        return 'blog-out-of-stock';
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }
}
