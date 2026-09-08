<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopId;

/**
 * @internal
 */
readonly class FingerprintComparisonResult
{
    public int $score;

    /**
     * @param array<string, FingerprintMatch> $matchingFingerprints
     * @param array<string, FingerprintMismatch> $mismatchingFingerprints
     */
    public function __construct(
        public array $matchingFingerprints,
        public array $mismatchingFingerprints,
        public int $threshold,
    ) {
        $this->score = array_sum(array_map(static fn (FingerprintMismatch $mismatch) => $mismatch->score, $mismatchingFingerprints));
    }

    public function getMismatchingFingerprint(string $identifier): ?FingerprintMismatch
    {
        return $this->mismatchingFingerprints[$identifier] ?? null;
    }

    public function isMatching(): bool
    {
        return $this->score < $this->threshold;
    }
}
