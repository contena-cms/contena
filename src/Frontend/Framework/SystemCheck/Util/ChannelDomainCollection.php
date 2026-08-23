<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<ChannelDomain>
 */
class ChannelDomainCollection extends Collection
{
    /**
     * @param list<ChannelDomain> $elements
     */
    public function __construct(array $elements)
    {
        $indexed = [];
        foreach ($elements as $element) {
            $indexed[$element->channelId] = $element;
        }

        parent::__construct($indexed);
    }

    protected function getExpectedClass(): string
    {
        return ChannelDomain::class;
    }
}
