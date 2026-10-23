<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl;


/**
 * The ACL roles of an admin user or integration. An admin's roles are unrestricted: they allow everything.
 *
 * @internal
 */
final readonly class AclRoles
{
    /**
     * @param list<string> $roleIds
     */
    public function __construct(
        public bool $unrestricted,
        public array $roleIds,
    ) {
    }

    /**
     * Keeps only the roles that are also in the given list. Unrestricted roles allow everything, so they become
     * exactly the given ones.
     *
     * @param list<string> $roleIds
     */
    public function intersect(array $roleIds): self
    {
        if ($this->unrestricted) {
            return new self(false, $roleIds);
        }

        return new self(false, array_values(array_intersect($this->roleIds, $roleIds)));
    }
}
