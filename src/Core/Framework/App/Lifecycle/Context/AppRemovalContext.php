<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Context;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
final readonly class AppRemovalContext
{
    public function __construct(
        public AppEntity $app,
        public Context $context,
        public bool $keepUserData = false,
    ) {
    }
}
