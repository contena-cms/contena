<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * This route allows changing configurations inside the context.
 * Following parameters are allowed to change: "languageId" and "countryId".
 */
abstract class AbstractContextSwitchRoute
{
    abstract public function getDecorated(): AbstractContextSwitchRoute;

    abstract public function switchContext(RequestDataBag $data, ChannelContext $context): ContextTokenResponse;
}
