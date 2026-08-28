<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ModifyJsonFieldExtension extends EntityExtension
{
    public function modifyFields(FieldCollection $collection): void
    {
        $data = $collection->get('data');
        if (!$data instanceof JsonField) {
            return;
        }

        $data->addPropertyMapping(new JsonField('extended', 'extended', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
            new IntField('maxSearchCount', 'maxSearchCount'),
        ]));
    }

    public function getEntityName(): string
    {
        return NestedDefinition::ENTITY_NAME;
    }
}
