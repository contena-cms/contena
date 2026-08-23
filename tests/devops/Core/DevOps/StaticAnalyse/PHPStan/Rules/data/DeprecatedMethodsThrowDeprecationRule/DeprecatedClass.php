<?php declare(strict_types=1);

namespace Contena\Core\DevOps\MyFakeNamespace;

use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.9.0 - Will be removed without replacement
 */
class DeprecatedClass
{
    public function __construct()
    {
    }

    public function publicMethodWithoutTrigger(): void
    {
    }

    public function publicMethodWithTrigger(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.9.0.0')
        );
    }
}
