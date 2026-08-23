<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Sync;

use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;

abstract class AbstractFkResolver
{
    /**
     * Returns the unique name for the resolver which is used to identify for fk resolving hash map
     */
    abstract public static function getName(): string;

    /**
     * @param array<FkReference> $map
     *
     * @throws EntityNotFoundException
     *
     * @return array<FkReference>
     */
    abstract public function resolve(array $map): array;
}
