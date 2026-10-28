<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\ArrayType;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class ArrayBusinessEvent implements FlowEventAware, BusinessEventEncoderTestInterface
{
    /**
     * @var list<TaxEntity>
     */
    private readonly array $taxes;

    public function __construct(TaxCollection $taxes)
    {
        $this->taxes = array_values($taxes->getElements());
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('taxes', new ArrayType(new EntityType(TaxDefinition::class)));
    }

    public function getEncodeValues(string $contenaVersion): array
    {
        $taxes = [];

        foreach ($this->taxes as $tax) {
            $taxes[] = [
                'id' => $tax->getId(),
                '_uniqueIdentifier' => $tax->getId(),
                'versionId' => null,
                'name' => $tax->getName(),
                'taxRate' => $tax->getTaxRate(),
                'position' => $tax->getPosition(),
                'customFields' => null,
                'translated' => [],
                'createdAt' => $tax->getCreatedAt() ? $tax->getCreatedAt()->format(\DATE_RFC3339_EXTENDED) : null,
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

    /**
     * @return list<TaxEntity>
     */
    public function getTaxes(): array
    {
        return $this->taxes;
    }
}
