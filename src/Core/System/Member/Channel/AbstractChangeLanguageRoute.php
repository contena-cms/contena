<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route is used to change the language of a logged-in user
 * The required field is: "languageId"
 */
abstract class AbstractChangeLanguageRoute
{
    abstract public function getDecorated(): AbstractChangeLanguageRoute;

    abstract public function change(RequestDataBag $requestDataBag, ChannelContext $context, MemberEntity $member): SuccessResponse;
}
