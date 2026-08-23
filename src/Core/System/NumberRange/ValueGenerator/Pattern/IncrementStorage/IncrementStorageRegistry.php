<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Contena\Core\System\NumberRange\NumberRangeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class IncrementStorageRegistry
{
    /**
     * @internal
     *
     * @param ServiceLocator<AbstractIncrementStorage> $storages
     */
    public function __construct(
        private readonly ServiceLocator $storages,
        private readonly string $configuredStorage
    ) {
    }

    public function getStorage(?string $storage = null): AbstractIncrementStorage
    {
        if ($storage === null) {
            $storage = $this->configuredStorage;
        }

        if (!$this->storages->has($storage)) {
            throw NumberRangeException::incrementStorageNotFound($storage, array_keys($this->storages->getProvidedServices()));
        }

        return $this->storages->get($storage);
    }

    public function migrate(string $from, string $to): void
    {
        $fromStorage = $this->getStorage($from);
        $toStorage = $this->getStorage($to);

        foreach ($fromStorage->list() as $numberRangeId => $state) {
            $toStorage->set($numberRangeId, $state);
        }
    }
}
