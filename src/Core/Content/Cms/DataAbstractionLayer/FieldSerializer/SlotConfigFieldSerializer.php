<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;

class SlotConfigFieldSerializer extends JsonFieldSerializer
{
    protected function getConstraints(Field $field): array
    {
        return [
            new All(
                constraints: new Collection(
                    fields: [
                        'source' => [
                            new Choice(
                                choices: [
                                    FieldConfig::SOURCE_STATIC,
                                    FieldConfig::SOURCE_MAPPED,
                                    FieldConfig::SOURCE_CONTENT_STREAM,
                                    FieldConfig::SOURCE_DEFAULT,
                                ]
                            ),
                            new NotBlank(),
                        ],
                        'value' => [],
                    ],
                    allowExtraFields: false,
                    allowMissingFields: false
                ),
            ),
        ];
    }
}
