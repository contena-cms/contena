<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;
use Contena\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Contena\Core\Framework\Util\Hasher;

class IterateEntityIndexerMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @internal
     *
     * @param array{offset: int|null}|null $offset
     * @param array<string> $skip
     */
    public function __construct(
        protected string $indexer,
        protected ?array $offset,
        protected array $skip = [],
        private readonly ?Context $context = null,
    ) {
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    /**
     * @return array{offset: int|null}|null
     */
    public function getOffset(): ?array
    {
        return $this->offset;
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    public function setOffset(?array $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * @return array<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createCLIContext();
    }

    public function deduplicationId(): ?string
    {
        $sortedSkip = $this->skip;
        sort($sortedSkip);

        $sortedOffset = $this->offset;
        if (\is_array($sortedOffset)) {
            ksort($sortedOffset);
        }

        $data = json_encode([
            $this->indexer,
            $sortedOffset,
            $sortedSkip,
            serialize($this->getContext()),
        ]);

        if ($data === false) {
            return null;
        }

        return Hasher::hash($data);
    }
}
