<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Binding\Specification;

/**
 * One `inputs` entry of a {@see BindingSpecification}. Presence is modeled explicitly (`hasDefault`)
 * so "no default" is distinct from "default is null". `$required` is derived from the wiring by the
 * canonicalizer (never authored).
 *
 * @internal
 */
final readonly class BindingInput
{
    public function __construct(
        public bool $hasDefault,
        public mixed $default,
        public bool $required,
    ) {
    }
}
