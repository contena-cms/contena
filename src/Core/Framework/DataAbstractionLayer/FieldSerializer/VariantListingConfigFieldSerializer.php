<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Content\Blog\DataAbstractionLayer\VariantListingConfig;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
class VariantListingConfigFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw DataAbstractionLayerException::invalidSerializerField(self::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();
        $value['displayParent'] = isset($value['displayParent']) ? (int) $value['displayParent'] : null;

        yield $field->getStorageName() => $value !== [] ? Json::encode($value) : null;
    }

    public function decode(Field $field, mixed $value): ?VariantListingConfig
    {
        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        return new VariantListingConfig(
            isset($value['displayParent']) ? (bool) $value['displayParent'] : null,
            $value['mainVariantId'] ?? null,
            $value['configuratorGroupConfig'] ?? null,
        );
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Collection(
                fields: [
                    'displayParent' => [new Type('boolean')],
                    'mainVariantId' => [new Uuid()],
                    'configuratorGroupConfig' => [
                        new Optional(
                            new Collection(
                                fields: [
                                    'id' => [new NotBlank(), new Uuid()],
                                    'representation' => [new NotBlank(), new Type('string')],
                                    'expressionForListings' => [new NotBlank(), new Type('boolean')],
                                ],
                                allowExtraFields: true,
                                allowMissingFields: true
                            )
                        ),
                    ],
                ],
                allowExtraFields: true,
                allowMissingFields: true
            ),
        ];
    }
}
