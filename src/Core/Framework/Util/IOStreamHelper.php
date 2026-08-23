<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Util;

final class IOStreamHelper
{
    public static function writeError(string $message, ?\Throwable $error = null): void
    {
        $errorMessage = '';
        if ($error !== null) {
            $errorMessage = ' Error message: ' . $error->getMessage();
        }

        if (\defined('\STDERR')) {
            fwrite(\STDERR, $message . $errorMessage . \PHP_EOL);
        }
    }
}
