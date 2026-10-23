<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

use Contena\Core\Framework\Log\Package;

class FormDataObjectType extends ObjectType
{
    final public const MARKER = 'formData';

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [self::MARKER => true]);
    }
}
