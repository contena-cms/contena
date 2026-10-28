<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EntityCollectionType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxDefinition;

/**
 * @internal
 */
class CollectionBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    public function __construct(private readonly TaxCollection $taxes)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('taxes', new EntityCollectionType(TaxDefinition::class));
    }

    public function getEncodeValues(string $contenaVersion): array
    {
        $taxes = [];

        foreach ($this->getTaxes() as $tax) {
            $taxes[] = [
                'id' => $tax->getId(),
                '_uniqueIdentifier' => $tax->getId(),
                'versionId' => null,
                'name' => $tax->getName(),
                'taxRate' => $tax->getTaxRate(),
                'position' => $tax->getPosition(),
                'customFields' => null,
                'translated' => [],
                'createdAt' => $tax->getCreatedAt()?->format(\DATE_RFC3339_EXTENDED),
                'updatedAt' => null,
                'apiAlias' => 'tax',
            ];
        }

        return [
            'taxes' => $taxes,
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

    public function getTaxes(): TaxCollection
    {
        return $this->taxes;
    }
}
