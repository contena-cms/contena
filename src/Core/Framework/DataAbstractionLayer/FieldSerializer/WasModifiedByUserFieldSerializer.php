<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\WasModifiedByUserField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class WasModifiedByUserFieldSerializer extends BoolFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof WasModifiedByUserField) {
            throw DataAbstractionLayerException::invalidSerializerField(WasModifiedByUserField::class, $field);
        }

        // reject explicit writes from any scope
        if ($data->getValue() !== null) {
            $violationList = new ConstraintViolationList();
            $violationList->add(
                new ConstraintViolation(
                    'This field is write-protected.',
                    'This field is write-protected.',
                    [],
                    $data->getValue(),
                    '/' . $data->getKey(),
                    $data->getValue()
                )
            );

            $parameters->getContext()->getExceptions()->add(
                new WriteConstraintViolationException($violationList, $parameters->getPath())
            );

            return;
        }

        $scope = $parameters->getContext()->getContext()->getScope();

        // user/api scope writes always mark the entity as user-modified
        if ($scope === Context::USER_SCOPE || $scope === Context::CRUD_API_SCOPE) {
            $data->setValue(true);

            yield from parent::encode($field, $existence, $data, $parameters);

            return;
        }

        // system scope: set false on create, do nothing on update
        if (!$existence->exists()) {
            $data->setValue(false);

            yield from parent::encode($field, $existence, $data, $parameters);
        }
    }
}
