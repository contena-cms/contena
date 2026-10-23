<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;

class SlotConfigFieldSerializer extends JsonFieldSerializer
{
    protected function getConstraints(Field $field): array
    {
        return [
        ];
    }
}
