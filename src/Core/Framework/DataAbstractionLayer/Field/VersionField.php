<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;

class VersionField extends FkField
{
    public function __construct()
    {
        parent::__construct('version_id', 'versionId', VersionDefinition::class);

        $this->addFlags(new PrimaryKey(), new Required());
    }

    protected function getSerializerClass(): string
    {
        return VersionFieldSerializer::class;
    }
}
