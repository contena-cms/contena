<?php
declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Framework\Log\Package;

class BlogNotFoundError extends Error
{
    public function __construct(protected string $id)
    {
        parent::__construct('The blog %s could not be found');
    }

    public function getParameters(): array
    {
        return ['id' => $this->id];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'blog-not-found';
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
