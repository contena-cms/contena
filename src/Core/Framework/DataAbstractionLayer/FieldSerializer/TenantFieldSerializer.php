<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Enforces the tenant isolation of {@see TenantField}:
 *
 * - tenant contexts inject their tenant id and reject payloads of other tenants,
 * - contexts without a tenant write platform-owned rows with a null tenant,
 * - tenant-owned rows can only be written from their tenant context,
 * - the stored tenant of an existing row can never be dropped or changed.
 *
 * @internal
 */
class TenantFieldSerializer extends FkFieldSerializer
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof TenantField) {
            throw DataAbstractionLayerException::invalidSerializerField(TenantField::class, $field);
        }

        $this->assertWritableTenant($parameters, $field->getPropertyName(), $data[$field->getPropertyName()] ?? null);

        return $data;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof TenantField) {
            throw DataAbstractionLayerException::invalidSerializerField(TenantField::class, $field);
        }

        $value = $data->getValue();

        $this->assertWritableTenant($parameters, $field->getPropertyName(), $value);

        $context = $parameters->getContext()->getContext();
        $tenantId = $context->getTenantId();

        if ($existence->exists()) {
            $original = $this->validateExistingOwnership($field, $existence, $parameters, $value);

            if ($value !== null && $value !== $original) {
                $this->throwViolation(
                    'The tenant of an existing row can not be changed.',
                    $field->getPropertyName(),
                    $value,
                    $parameters,
                );
            }

            // Preserve both tenant and platform ownership when an update omits
            // the field. A tenant context can therefore never claim a platform row.
            $data->setValue($original);
        } elseif ($tenantId !== null && $data->getValue() === null) {
            $data->setValue($tenantId);
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function validateExistingOwnership(
        TenantField $field,
        EntityExistence $existence,
        WriteParameterBag $parameters,
        mixed $value = null,
    ): ?string {
        $original = $existence->getState()[$field->getStorageName()] ?? null;
        $original = $original === null ? null : $this->toHex($original);

        if ($parameters->getContext()->getContext()->getTenantId() !== $original) {
            $this->throwViolation(
                'The existing row does not belong to the current tenant context.',
                $field->getPropertyName(),
                $value,
                $parameters,
            );
        }

        return $original;
    }

    private function assertWritableTenant(WriteParameterBag $parameters, string $propertyName, mixed $value): void
    {
        $context = $parameters->getContext()->getContext();

        $tenantId = $context->getTenantId();
        if ($tenantId === null && $value !== null) {
            $this->throwViolation(
                'Creating tenant-owned data requires the target tenant context.',
                $propertyName,
                $value,
                $parameters,
            );
        }

        if ($tenantId !== null && $value !== null && $value !== $tenantId) {
            $this->throwViolation(
                'The tenant of the payload does not match the tenant of the current context.',
                $propertyName,
                $value,
                $parameters,
            );
        }
    }

    /**
     * Normalizes a stored raw value (binary or hex) into the hex representation.
     */
    private function toHex(mixed $value): string
    {
        if (\is_string($value) && \strlen($value) === 16) {
            return Uuid::fromBytesToHex($value);
        }

        return (string) $value;
    }

    private function throwViolation(string $message, string $propertyName, mixed $value, WriteParameterBag $parameters): never
    {
        throw new WriteConstraintViolationException(new ConstraintViolationList([
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
