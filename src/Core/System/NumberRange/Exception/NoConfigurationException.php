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
class NoConfigurationException extends NumberRangeException
{
    public function __construct(
        string $entityName,
        ?string $channelId = null
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', NumberRangeException::class)
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::NO_CONFIGURATION_FOR_ENTITY,
            'No number range configuration found for entity "{{ entity }}" with sales channel "{{ channelId }}".',
            ['entity' => $entityName, 'channelId' => $channelId]
        );
    }
}
