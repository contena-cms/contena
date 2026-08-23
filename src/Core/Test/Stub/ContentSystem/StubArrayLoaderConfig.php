<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * A data loader config that round-trips an arbitrary array through jsonSerialize(), for tests that need the
 * decode()/encode() cycle to carry real values (e.g. structural config comparison) rather than the fixed empty
 * shape {@see StubLoaderConfig} returns.
 *
 * @final
 */
readonly class StubArrayLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
