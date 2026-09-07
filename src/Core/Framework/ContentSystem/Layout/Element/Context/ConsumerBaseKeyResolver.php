<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Context;

/**
 * The property key a consumer writes reduced to the write boundary's uniqueness axis: the key's first dotted segment, or the whole key when it carries no dot.
 *
 * @internal
 */
final readonly class ConsumerBaseKeyResolver
{
    public function resolve(string $key): string
    {
        $position = strpos($key, '.');

        return $position === false ? $key : substr($key, 0, $position);
    }
}
