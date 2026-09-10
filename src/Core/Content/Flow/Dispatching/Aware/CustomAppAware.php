<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
interface CustomAppAware
{
    public const string CUSTOM_DATA = 'customAppData';

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAppData(): ?array;
}
