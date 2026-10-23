<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\System\NumberRange\Exception\IncrementStorageNotFoundException;

/**
 * @codeCoverageIgnore
 */
class NumberRangeException extends HttpException
{
    public const INCREMENT_STORAGE_NOT_FOUND = 'FRAMEWORK__INCREMENT_STORAGE_NOT_FOUND';
    public const NO_CONFIGURATION_FOR_ENTITY = 'FRAMEWORK__NO_NUMBER_RANGE_CONFIGURATION';
    public const NUMBER_RANGE_NOT_FOUND = 'FRAMEWORK__NUMBER_RANGE_NOT_FOUND';

    /**
     * @param array<string> $availableStorages
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function incrementStorageNotFound(string $storage, array $availableStorages): self|IncrementStorageNotFoundException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new IncrementStorageNotFoundException($storage, $availableStorages);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INCREMENT_STORAGE_NOT_FOUND,
            'The number range increment storage "{{ storage }}" is not available. Available storages are: "{{ availableStorages }}".',
            ['storage' => $storage, 'availableStorages' => implode('", "', $availableStorages)]
        );
    }

    public static function noConfigurationForEntity(string $entity, ?string $channelId = null): self
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new NoConfigurationException($entity, $channelId);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_CONFIGURATION_FOR_ENTITY,
            'No number range configuration found for entity "{{ entity }}" with sales channel "{{ channelId }}".',
            ['entity' => $entity, 'channelId' => $channelId]
        );
    }

    public static function numberRangeNotFound(string $numberRangeId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NUMBER_RANGE_NOT_FOUND,
            'Number range with id "{{ numberRangeId }}" was not found.',
            ['numberRangeId' => $numberRangeId]
        );
    }
}
