<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Framework\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
{
    public const string ENTITY_NAME = 'test_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey()),
            new IdField('idAllowHtml', 'idAllowHtml')->addFlags(new AllowHtml(false)),
            new IdField('idAllowHtmlSanitized', 'idAllowHtmlSanitized')->addFlags(new AllowHtml(true)),
        ]);
    }
}
