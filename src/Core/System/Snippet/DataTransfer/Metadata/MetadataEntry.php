<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\Metadata;


/**
 * @internal
 */
class MetadataEntry
{
    use JsonSerializableTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;

    private function __construct(
        public readonly string $locale,
        public readonly \DateTime $updatedAt,
        public readonly int $progress,
        public bool $isUpdateRequired = false,
    ) {
    }

    /**
     * @param array{locale: string, updatedAt: string, progress: int} $data
     */
    public static function create(array $data): self
    {
        return new self(
            locale: $data['locale'],
            updatedAt: new \DateTime($data['updatedAt']),
            progress: $data['progress'],
        );
    }

    public function markForUpdate(): void
    {
        $this->isUpdateRequired = true;
    }
}
