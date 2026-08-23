<?php

declare(strict_types=1);

namespace Contena\Core\System\Channel\Validation;

use Contena\Core\Framework\Struct\Collection;
use Contena\Core\System\Channel\ChannelException;

/**
 * @internal
 *
 * @extends Collection<ChannelData>
 */
class Mapping extends Collection
{
    /**
     * @param iterable<string, ChannelData> $elements indexed by channel ID
     */
    public function __construct(iterable $elements = [])
    {
        parent::__construct($elements);
    }

    public function add($element): void
    {
        throw ChannelException::invalidMappingOperation('ChannelData needs to be added indexed by channel ID. Use set() instead.');
    }

    /**
     * @param string $key channel ID
     * @param ChannelData $element
     */
    public function set($key, $element): void
    {
        parent::set($key, $element);
    }

    protected function getExpectedClass(): string
    {
        return ChannelData::class;
    }
}
