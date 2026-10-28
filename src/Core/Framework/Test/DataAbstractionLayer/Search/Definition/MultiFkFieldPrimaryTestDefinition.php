<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Search\Definition;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class MultiFkFieldPrimaryTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'multi_fk_field_primary';
    }

    public function since(): string
    {
        return '6.4.1';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('first_id', 'firstId'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new IdField('second_id', 'secondId'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
        ]);
    }
}
