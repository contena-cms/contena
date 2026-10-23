<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Symfony\Contracts\Service\ResetInterface;

abstract class AbstractEntitySerializer implements ResetInterface
{
    protected SerializerRegistry $serializerRegistry;

    /**
     * @param array<string, mixed>|Struct|null $entity
     *
     * @return iterable<string, mixed>
     */
    abstract public function serialize(Config $config, EntityDefinition $definition, $entity): iterable;

    /**
     * @param iterable<string, mixed> $entity
     *
     * @return iterable<string, mixed>
     */
    abstract public function deserialize(Config $config, EntityDefinition $definition, $entity);

    abstract public function supports(string $entity): bool;

    public function setRegistry(SerializerRegistry $serializerRegistry): void
    {
        $this->serializerRegistry = $serializerRegistry;
    }

    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    protected function getDecorated(): AbstractEntitySerializer
    {
        throw new DecorationPatternException(self::class);
    }
}
