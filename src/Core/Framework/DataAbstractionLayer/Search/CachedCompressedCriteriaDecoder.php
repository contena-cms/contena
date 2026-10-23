<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class CachedCompressedCriteriaDecoder extends CompressedCriteriaDecoder implements ResetInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly CompressedCriteriaDecoder $decorated
    ) {
    }

    public function decode(string $encodedCriteria): array
    {
        if (isset($this->cache[$encodedCriteria])) {
            return $this->cache[$encodedCriteria];
        }

        return $this->cache[$encodedCriteria] = $this->decorated->decode($encodedCriteria);
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
