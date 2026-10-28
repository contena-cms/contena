<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\ObjectType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class NestedEntityBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    public function __construct(private readonly TaxEntity $tax)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('object', (new ObjectType())
                ->add('tax', new EntityType(TaxDefinition::class)));
    }

    public function getEncodeValues(string $contenaVersion): array
    {
        return [
            'object' => [
                'tax' => [
                    'id' => $this->tax->getId(),
                    '_uniqueIdentifier' => $this->tax->getId(),
                    'versionId' => null,
                    'name' => $this->tax->getName(),
                    'taxRate' => $this->tax->getTaxRate(),
                    'position' => $this->tax->getPosition(),
                    'customFields' => null,
                    'translated' => [],
                    'createdAt' => $this->tax->getCreatedAt() ? $this->tax->getCreatedAt()->format(\DATE_RFC3339_EXTENDED) : null,
                    'updatedAt' => null,
                    'apiAlias' => 'tax',
                ],
            ],
        ];
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public function getObject(): EntityBusinessEvent
    {
        return new EntityBusinessEvent($this->tax);
    }
}
