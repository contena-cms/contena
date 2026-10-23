<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Context;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;

/**
 * @codeCoverageIgnore
 *
 * @internal only for use by the app-system
 */
final readonly class AppActivationContext
{
    public function __construct(
        public AppEntity $app,
        public Context $context,
    ) {
    }
}
