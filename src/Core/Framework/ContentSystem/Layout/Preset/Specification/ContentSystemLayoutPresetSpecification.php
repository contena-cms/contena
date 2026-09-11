<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Preset\Specification;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class ContentSystemLayoutPresetSpecification
{
    /**
     * @param list<array<string, mixed>> $payload
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $icon,
        public array $payload,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'payload' => $this->payload,
        ];
    }
}
