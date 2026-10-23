<?php declare(strict_types=1);

namespace Contena\Core\System\User\Password;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Password\LegacyEncoder\LegacyEncoderInterface;
use Contena\Core\Framework\Log\Package;

class LegacyPasswordVerifier
{
    /**
     * @internal
     *
     * @param LegacyEncoderInterface[] $encoder
     */
    public function __construct(private readonly iterable $encoder)
    {
    }

    public function verify(#[\SensitiveParameter] string $password, UserEntity $user): bool
    {
        if (!$user->getLegacyEncoder() || !$user->getLegacyPassword()) {
            throw UserException::badCredentials();
        }

        foreach ($this->encoder as $encoder) {
            if ($encoder->getName() !== $user->getLegacyEncoder()) {
                continue;
            }

            return $encoder->isPasswordValid($password, $user->getLegacyPassword());
        }

        throw UserException::legacyPasswordEncoderNotFound($user->getLegacyEncoder());
    }
}
