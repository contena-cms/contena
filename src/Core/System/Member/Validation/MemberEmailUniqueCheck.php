<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation;

/**
 * @codeCoverageIgnore Simple struct with public readonly properties.
 */
final class MemberEmailUniqueCheck
{
    public function __construct(
        public readonly string $email,
        public readonly string $channelId,
        public readonly ?string $memberId = null,
    ) {
    }
}
