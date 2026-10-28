<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class UpdatedByFieldSerializer extends FkFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof UpdatedByField) {
            throw DataAbstractionLayerException::invalidSerializerField(UpdatedByField::class, $field);
        }

        if (!$existence->exists()) {
            return;
        }

        $context = $parameters->getContext()->getContext();
        $scope = $context->getScope();

        if (!\in_array($scope, $field->getAllowedWriteScopes(), true)) {
            return;
        }

        if (!$context->getSource() instanceof AdminApiSource) {
            return;
        }

        $userId = $context->getSource()->getUserId();

        /** @deprecated tag:v6.8.0 - remove early return */
        if (!$userId && !Feature::isActive('v6.8.0.0')) {
            return;
        }

        $data->setValue($userId);

        yield from parent::encode($field, $existence, $data, $parameters);
    }
}
