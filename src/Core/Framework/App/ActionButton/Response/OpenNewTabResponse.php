<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ActionButton\Response;

/**
 * @internal only for use by the app-system
 */
class OpenNewTabResponse extends ActionButtonResponse
{
    final public const string ACTION_TYPE = 'openNewTab';

    /**
     * This is the URL the user is redirected to after the action has been taken.
     */
    protected string $redirectUrl = '';

    public function __construct()
    {
        parent::__construct(self::ACTION_TYPE);
    }
}
