<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * In case a column is allowed to contain HTML-esque data. Beware of injection possibilities
 */
class AllowHtml extends Flag
{
    public function __construct(protected bool $sanitized = true)
    {
    }

    public function parse(): \Generator
    {
        yield 'allow_html' => true;
    }

    public function isSanitized(): bool
    {
        return $this->sanitized;
    }
}
