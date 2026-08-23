<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Entity whose PascalCase schema name contains consecutive capital letters
 * (SEOUrl <-> s_e_o_url) to cover the PascalCase to snake_case conversion of acronyms.
 *
 * @internal
 */
class SEOUrlDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 's_e_o_url';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware()),
            new ManyToOneAssociationField(
                'simpleThings',
                'simple_id',
                SimpleDefinition::class,
                'id'
            )->addFlags(new ApiAware(ChannelApiSource::class)),
        ]);
    }
}
