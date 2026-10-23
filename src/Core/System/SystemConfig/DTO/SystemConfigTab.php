<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\DTO;


/**
 * @codeCoverageIgnore
 */
class SystemConfigTab
{
    /**
     * @param list<SystemConfigCard> $cards
     * @param array<string, string|null>|null $title
     */
    public function __construct(
        public readonly array $cards,
        public readonly ?array $title = null,
        public readonly ?string $name = null
    ) {
    }
}
