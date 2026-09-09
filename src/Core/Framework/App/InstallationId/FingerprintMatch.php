<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationId;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class FingerprintMatch
{
    public function __construct(
        public string $identifier,
        public string $storedStamp,
        public int $score,
    ) {
    }

    public static function fromFingerprint(Fingerprint $fingerprint): self
    {
        return new self(
            $fingerprint->getIdentifier(),
            $fingerprint->getStamp(),
            $fingerprint->getScore()
        );
    }
}
