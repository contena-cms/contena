<?php declare(strict_types=1);

namespace Contena\Core\System\User\Password\LegacyEncoder;

use Contena\Core\Framework\Log\Package;

interface LegacyEncoderInterface
{
    public function getName(): string;

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool;
}
