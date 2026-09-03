<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Allows this foreign key on a tenant-owned row to reference a platform-owned row.
 *
 * The flag changes reference validation only. It does not make the referenced row
 * readable or writable from a tenant context.
 */
class AllowPlatformOwnedReference extends Flag
{
    public function parse(): \Generator
    {
        yield 'allow_platform_owned_reference' => true;
    }
}
