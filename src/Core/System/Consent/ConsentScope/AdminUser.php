<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\ConsentScope;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\System\Consent\ConsentException;
use Contena\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
class AdminUser implements ConsentScope
{
    public const string NAME = 'admin_user';

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

        $userId = $source->getUserId();
        if (!$userId) {
            throw ConsentException::cannotResolveScope(self::NAME);
        }

        return $userId;
    }

    public function resolveActorIdentifier(Context $context): string
    {
        return $this->resolveIdentifier($context);
    }
}
