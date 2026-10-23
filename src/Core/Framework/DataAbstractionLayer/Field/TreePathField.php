<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class TreePathField extends LongTextField
{
    public function __construct(
        string $storageName,
        string $propertyName,
        private readonly string $pathField = 'id'
    ) {
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new WriteProtected(Context::SYSTEM_SCOPE));
    }

    public function getPathField(): string
    {
        return $this->pathField;
    }
}
