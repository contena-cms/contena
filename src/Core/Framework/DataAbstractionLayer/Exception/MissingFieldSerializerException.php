<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class MissingFieldSerializerException extends ContenaHttpException
{
    public function __construct(Field $field)
    {
        parent::__construct('No field serializer class found for field class "{{ class }}".', ['class' => $field::class]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MISSING_FIELD_SERIALIZER';
    }
}
