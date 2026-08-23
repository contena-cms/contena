<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

interface FieldEnumProviderInterface
{
    public function isSupported(string $entity, string $fieldName): bool;

    /**
     * @return list<string|bool|int|float>
     */
    public function getChoices(): array;
}
