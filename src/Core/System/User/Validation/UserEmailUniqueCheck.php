<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation;


/**
 * @codeCoverageIgnore Simple struct with public readonly properties.
 */
final class UserEmailUniqueCheck
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $userId = null,
        public readonly ?string $boundChannelId = null,
        public readonly bool $guest = false,
    ) {
    }
}
