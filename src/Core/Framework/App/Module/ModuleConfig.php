<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Module;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;

/**
 * The admin modules an app declares: the module entries plus the optional main module. One per app.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
readonly class ModuleConfig implements AppFeatureConfig
{
    /**
     * @param list<Module> $modules
     */
    public function __construct(
        public array $modules,
        public ?MainModule $mainModule,
    ) {
    }

    public function getName(): string
    {
        return 'admin';
    }
}
