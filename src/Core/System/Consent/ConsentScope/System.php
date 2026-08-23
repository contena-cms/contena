<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\ConsentScope;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\System\Consent\ConsentException;
use Contena\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
class System implements ConsentScope
{
    public const string NAME = 'system';

    public function getName(): string
    {
        return self::NAME;
    }

    public function resolveIdentifier(Context $context): string
    {
        return self::NAME;
    }

    /**
     * This consent is scoped to the system, but a particular admin user performed the action
     */
    public function resolveActorIdentifier(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        $userId = $source->getUserId();
        if (!$userId) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return $userId;
    }
}
