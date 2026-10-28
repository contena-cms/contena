<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, use SystemConfigException::invalidSettingValueException() instead
 */
class InvalidSettingValueException extends SystemConfigException
{
    public function __construct(
        string $key,
        ?string $neededType = null,
        ?string $actualType = null
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'SystemConfigException::invalidSettingValueException()')
        );

        $message = 'Invalid value for \'{{ key }}\'';
        if ($neededType !== null) {
            $message .= '. Must be of type \'{{ neededType }}\'';
        }
        if ($actualType !== null) {
            $message .= '. But is of type \'{{ actualType }}\'';
        }

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SETTING_VALUE,
            $message,
            [
                'key' => $key,
                'neededType' => $neededType,
                'actualType' => $actualType,
            ]
        );
    }
}
