<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
/**
 * @deprecated tag:v6.8.0 - Will be removed, use SystemConfigException::invalidDomain() instead
 */
class InvalidDomainException extends SystemConfigException
{
    public function __construct(string $domain)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'SystemConfigException::invalidDomain()')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain \'{{ domain }}\'',
            ['domain' => $domain]
        );
    }
}
