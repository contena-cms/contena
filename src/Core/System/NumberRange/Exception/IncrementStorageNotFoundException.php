<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\System\NumberRange\NumberRangeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use NumberRangeException::incrementStorageNotFound() instead
 *
 * @codeCoverageIgnore
 */
class IncrementStorageNotFoundException extends NumberRangeException
{
    /**
     * @param array<string> $availableStorages
     */
    public function __construct(
        string $configuredStorage,
        array $availableStorages = []
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', NumberRangeException::class)
        );

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INCREMENT_STORAGE_NOT_FOUND,
            'The number range increment storage "{{ configuredStorage }}" is not available. Available storages are: "{{ availableStorages }}".',
            [
                'configuredStorage' => $configuredStorage,
                'availableStorages' => implode('", "', $availableStorages),
            ]
        );
    }
}
