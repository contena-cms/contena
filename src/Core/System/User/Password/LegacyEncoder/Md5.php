<?php declare(strict_types=1);

namespace Contena\Core\System\User\Password\LegacyEncoder;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Hasher;

class Md5 implements LegacyEncoderInterface
{
    public function getName(): string
    {
        return 'Md5';
    }

    public function isPasswordValid(#[\SensitiveParameter] string $password, string $hash): bool
    {
        if (mb_strpos($hash, ':') === false) {
            return hash_equals($hash, Hasher::hash($password, 'md5'));
        }
        [$md5, $salt] = explode(':', $hash);

        return hash_equals($md5, Hasher::hash($password . $salt, 'md5'));
    }
}
