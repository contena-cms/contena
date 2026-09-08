<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton\Response;

use Contena\Core\Framework\App\ActionButton\AppAction;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class NotificationResponseFactory implements ActionButtonResponseFactoryInterface
{
    public function supports(string $actionType): bool
    {
        return $actionType === NotificationResponse::ACTION_TYPE;
    }

    public function create(AppAction $action, array $payload, Context $context): ActionButtonResponse
    {
        $response = new NotificationResponse();
        $response->assign($payload);

        return $response;
    }
}
