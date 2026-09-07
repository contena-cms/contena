<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Context;

use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;

final readonly class ContextConsumer implements \JsonSerializable
{
    public function __construct(
        public ContextType $type,
        public bool $required,
        public bool $redistribute = false,
        public ?string $consumerAlias = null,
        public ?string $propertyAlias = null,
        public ConsumerScope $scope = ConsumerScope::Parent
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type->value,
            'required' => $this->required,
        ];

        if ($this->redistribute) {
            $data['redistribute'] = true;
        }

        if ($this->consumerAlias !== null) {
            $data['consumerAlias'] = $this->consumerAlias;
        }

        if ($this->propertyAlias !== null) {
            $data['propertyAlias'] = $this->propertyAlias;
        }

        if ($this->scope !== ConsumerScope::Parent) {
            $data['scope'] = $this->scope->value;
        }

        return $data;
    }
}
