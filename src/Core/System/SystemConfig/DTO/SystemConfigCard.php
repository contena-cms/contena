<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\DTO;


/**
 * @codeCoverageIgnore
 */
class SystemConfigCard
{
    /**
     * @param list<SystemConfigElement> $elements
     * @param array<string, string|null> $title
     * @param array<string, string|null>|null $subtitle
     */
    public function __construct(
        public readonly array $elements,
        public readonly array $title,
        public readonly ?array $subtitle = null,
        public readonly ?string $name = null,
        public readonly ?string $flag = null
    ) {
    }
}
