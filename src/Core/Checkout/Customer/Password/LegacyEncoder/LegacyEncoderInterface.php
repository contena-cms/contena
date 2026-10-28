<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Password\LegacyEncoder;

use Contena\Core\Framework\Log\Package;

#[Package('checkout')]
interface LegacyEncoderInterface
{
    public function getName(): string;

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool;
}
