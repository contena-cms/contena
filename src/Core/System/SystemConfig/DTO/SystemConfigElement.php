<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\DTO;


/**
 * @codeCoverageIgnore
 */
class SystemConfigElement
{
    /**
     * @param array<string, mixed> $config
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        public readonly string $name,
        public readonly array $config,
        public readonly ?string $type = null,
        public mixed $value = null
    ) {
    }
}
