<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Content\Flow\FlowException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
class FlowTemplateConfigFieldSerializer extends JsonFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof StorageAware) {
            throw FlowException::invalidSerializerField(self::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);
        $value = $data->getValue();
        if (!\is_array($value)) {
            yield $field->getStorageName() => null;

            return;
        }

        $value = array_merge(['description' => null, 'sequences' => []], $value);
        $value['sequences'] = array_map(static fn (array $item): array => array_merge([
            'parentId' => null,
            'ruleId' => null,
            'position' => 1,
            'displayGroup' => 1,
            'trueCase' => false,
        ], $item), $value['sequences']);

        yield $field->getStorageName() => Json::encode($value);
    }

    protected function getConstraints(Field $field): array
    {
        return [new Collection(fields: [
            'eventName' => [new NotBlank(), new Type('string')],
            'description' => [new Type('string')],
            'sequences' => [new All(constraints: [new Optional(new Collection(fields: [
                'id' => [new NotBlank(), new Uuid()],
                'actionName' => [new NotBlank(), new Type('string')],
                'parentId' => [new Uuid()],
                'ruleId' => [new Uuid()],
                'position' => [new Type('numeric')],
                'trueCase' => [new Type('boolean')],
                'displayGroup' => [new Type('numeric')],
                'config' => [new Type('array')],
            ], allowExtraFields: true, allowMissingFields: false))])],
        ], allowExtraFields: true, allowMissingFields: false)];
    }
}
