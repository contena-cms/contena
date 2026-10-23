<?php declare(strict_types=1);

namespace Contena\Core\System\User\Password\LegacyEncoder;


interface LegacyEncoderInterface
{
    public function getName(): string;

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool;
}
