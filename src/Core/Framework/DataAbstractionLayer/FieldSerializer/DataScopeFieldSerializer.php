<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Injects and enforces the exact, non-null owner carried by Context.
 * Cross-scope read capability never broadens this write boundary.
 *
 * @internal
 */
class DataScopeFieldSerializer extends FkFieldSerializer
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof DataScopeField) {
            throw DataAbstractionLayerException::invalidSerializerField(DataScopeField::class, $field);
        }

        $property = $field->getPropertyName();
        $data[$property] ??= $parameters->getContext()->getContext()->getDataScopeId();

        $this->assertMatchesContext($parameters, $property, $data[$property]);

        return parent::normalize($field, $data, $parameters);
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof DataScopeField) {
            throw DataAbstractionLayerException::invalidSerializerField(DataScopeField::class, $field);
        }

        $this->assertMatchesContext($parameters, $field->getPropertyName(), $data->getValue());

        if ($existence->exists()) {
            $data->setValue($this->validateExistingOwnership($field, $existence, $parameters, $data->getValue()));
        } else {
            $data->setValue($parameters->getContext()->getContext()->getDataScopeId());
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function validateExistingOwnership(
        DataScopeField $field,
        EntityExistence $existence,
        WriteParameterBag $parameters,
        mixed $value = null,
    ): string {
        $original = $existence->getState()[$field->getStorageName()] ?? null;

        if ($original === null) {
            $this->throwViolation(
                'The existing row has no data scope.',
                $field->getPropertyName(),
                $value,
                $parameters,
            );
        }

        $original = $this->toHex($original);
        if ($parameters->getContext()->getContext()->getDataScopeId() !== $original) {
            $this->throwViolation(
                'The existing row does not belong to the current data scope.',
                $field->getPropertyName(),
                $value,
                $parameters,
            );
        }

        return $original;
    }

    private function assertMatchesContext(WriteParameterBag $parameters, string $propertyName, mixed $value): void
    {
        if ($value !== null && $value !== $parameters->getContext()->getContext()->getDataScopeId()) {
            $this->throwViolation(
                'The data scope in the payload does not match the current context.',
                $propertyName,
                $value,
                $parameters,
            );
        }
    }

    private function toHex(mixed $value): string
    {
        if (\is_string($value) && \strlen($value) === 16) {
            return Uuid::fromBytesToHex($value);
        }

        return (string) $value;
    }

    private function throwViolation(string $message, string $propertyName, mixed $value, WriteParameterBag $parameters): never
    {
        throw DataAbstractionLayerException::invalidWriteConstraintViolation(new ConstraintViolationList([
            new ConstraintViolation(
                $message,
                $message,
                [],
                null,
                '/' . $propertyName,
                $value,
            ),
        ]), $parameters->getPath());
    }
}
