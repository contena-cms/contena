<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;

final class BlogTypeRegistry implements FieldEnumProviderInterface
{
    /**
     * @var list<string>
     */
    private array $types;

    /**
     * @internal
     *
     * @param list<string> $types
     */
    public function __construct(array $types)
    {
        $this->types = array_values(array_unique($types));
    }

    public function addType(string $type): void
    {
        if ($this->hasType($type)) {
            return;
        }

        $this->types[] = $type;
    }

    /**
     * @return list<string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function hasType(string $type): bool
    {
        return \in_array($type, $this->types, true);
    }

    public function isSupported(string $entity, string $fieldName): bool
    {
        return $entity === BlogDefinition::ENTITY_NAME && $fieldName === 'type';
    }

    /**
     * {@inheritDoc}
     */
    public function getChoices(): array
    {
        return $this->types;
    }
}
