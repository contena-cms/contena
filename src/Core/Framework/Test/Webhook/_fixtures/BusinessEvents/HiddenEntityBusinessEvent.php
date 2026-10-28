<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class HiddenEntityBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    public function __construct(private readonly TaxEntity $tax)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('tax', new EntityType(TaxDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]);
    }

    public function getEncodeValues(string $contenaVersion): array
    {
        return [];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public function getTax(): TaxEntity
    {
        return $this->tax;
    }
}
