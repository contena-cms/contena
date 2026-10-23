<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\ConsentScope;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\System\Consent\ConsentException;
use Contena\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
class AdminAdminUser implements ConsentScope
{
    public const NAME = 'admin_user';

    public function getName(): string
    {
        return self::NAME;
    }

    public function resolveIdentifier(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        $admin_userId = $source->getUserId();
        if (!$admin_userId) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return $admin_userId;
    }

    public function resolveActorIdentifier(Context $context): string
    {
        return $this->resolveIdentifier($context);
    }
}
