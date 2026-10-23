<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\DTO;

use Contena\Core\System\Consent\ConsentStatus;

/**
 * @codeCoverageIgnore
 */
class ConsentStateRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly ConsentStatus $status,
        public readonly string $actor,
        public readonly string $updatedAt,
        public readonly ?string $revision = null,
    ) {
    }
}
