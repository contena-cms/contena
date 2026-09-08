<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Api;

use Contena\Core\Framework\Context;

/**
 * The `acl` service allows you to check if your app has been granted the specified privilege.
 *
 * @script-service miscellaneous
 */
class AclFacade
{
    public function __construct(private readonly Context $appContext)
    {
    }

    /**
     * The `can()` method allows you to check if your app has been granted the specified privilege.
     *
     * @param string $privilege The privilege you wish to check
     *
     * @return bool Returns `true` if the privilege has been granted, `false` otherwise.
     *
     * @example /acl/check.twig Check for the `blog:read` permission and query a blog if the permission is granted.
     */
    public function can(string $privilege): bool
    {
        return $this->appContext->isAllowed($privilege);
    }
}
