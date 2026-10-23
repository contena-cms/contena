<?php declare(strict_types=1);

namespace Contena\Core\Framework;

use Contena\Core\Framework\Log\Package;

interface ContenaException extends \Throwable
{
    public function getErrorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array;
}
