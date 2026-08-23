<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal;

interface ExceptionHandlerInterface
{
    public const int PRIORITY_DEFAULT = 0;

    public const int PRIORITY_LATE = -10;

    public const int PRIORITY_EARLY = 10;

    public function getPriority(): int;

    public function matchException(\Throwable $e): ?\Throwable;
}
