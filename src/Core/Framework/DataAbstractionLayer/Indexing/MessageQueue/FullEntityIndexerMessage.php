<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;
use Contena\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Contena\Core\Framework\Util\Hasher;

class FullEntityIndexerMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @internal
     *
     * @param list<string> $skip
     * @param list<string> $only
     */
    public function __construct(
        protected array $skip = [],
        protected array $only = [],
        private readonly ?Context $context = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }

    /**
     * @return list<string>
     */
    public function getOnly(): array
    {
        return $this->only;
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createCLIContext();
    }

    public function deduplicationId(): ?string
    {
        $sortedSkip = $this->skip;
        sort($sortedSkip);

        $sortedOnly = $this->only;
        sort($sortedOnly);

        $data = json_encode([
            $sortedSkip,
            $sortedOnly,
            serialize($this->getContext()),
        ]);

        if ($data === false) {
            return null;
        }

        return Hasher::hash($data);
    }
}
