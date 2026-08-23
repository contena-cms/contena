<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
interface ScalarValuesAware
{
    public const string STORE_VALUES = 'store_values';

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array;
}
