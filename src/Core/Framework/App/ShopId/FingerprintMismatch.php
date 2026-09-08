<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopId;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class FingerprintMismatch
{
    public function __construct(
        public string $identifier,
        public ?string $storedStamp,
        public string $expectedStamp,
        public int $score,
    ) {
    }
}
