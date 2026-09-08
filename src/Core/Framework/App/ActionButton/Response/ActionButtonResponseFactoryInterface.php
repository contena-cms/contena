<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton\Response;

use Contena\Core\Framework\App\ActionButton\AppAction;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
interface ActionButtonResponseFactoryInterface
{
    public function supports(string $actionType): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse;
}
