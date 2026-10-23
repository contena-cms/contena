<?php declare(strict_types=1);

namespace Contena\Core\System\User\Password\LegacyEncoder;

use Contena\Core\Framework\Util\Hasher;

class Sha256 implements LegacyEncoderInterface
{
    public function getName(): string
    {
        return 'Sha256';
    }

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool
    {
        [$iterations, $salt] = explode(':', $hash);

        $verifyHash = $this->generateInternal($password, $salt, (int) $iterations);

        return hash_equals($hash, $verifyHash);
    }

    private function generateInternal(#[\SensitiveParameter] string $password, string $salt, int $iterations): string
    {
        $hash = '';
        for ($i = 0; $i <= $iterations; ++$i) {
            $hash = Hasher::hash($hash . $password . $salt, 'sha256');
        }

        return $iterations . ':' . $salt . ':' . $hash;
    }
}
