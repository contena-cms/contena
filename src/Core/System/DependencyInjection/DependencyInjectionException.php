<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class DependencyInjectionException extends HttpException
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public const NUMBER_RANGE_REDIS_NOT_CONFIGURED = 'SYSTEM__NUMBER_RANGE_REDIS_NOT_CONFIGURED';

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public static function redisNotConfiguredForNumberRangeIncrementer(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'),
        );

        return new self(
            500,
            self::NUMBER_RANGE_REDIS_NOT_CONFIGURED,
            'Parameter "contena.number_range.config.connection" is required for redis storage'
        );
    }

    public static function definitionNotFound(string $entity): DefinitionNotFoundException
    {
        return new DefinitionNotFoundException($entity);
    }
}
