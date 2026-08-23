<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Event\FlowEventAware;

class ScalarValuesStorer extends FlowStorer
{
    public function store(FlowEventAware $event, array $stored): array
    {
        if ($event instanceof ScalarValuesAware) {
            $stored[ScalarValuesAware::STORE_VALUES] = $event->getValues();
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        $values = $storable->getStore(ScalarValuesAware::STORE_VALUES, []);
        if (!\is_array($values)) {
            return;
        }
        foreach ($values as $key => $value) {
            if (\is_string($key)) {
                $storable->setData($key, $value);
            }
        }
    }
}
