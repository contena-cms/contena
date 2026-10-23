<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton\Response;

use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
class OpenNewTabResponse extends ActionButtonResponse
{
    final public const ACTION_TYPE = 'openNewTab';

    /**
     * This is the URL the admin_user is redirected to after the action has been taken.
     */
    protected string $redirectUrl = '';

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
