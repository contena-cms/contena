<?php declare(strict_types=1);

use Contena\Core\System\Channel\ChannelContext;

function invalidAclInFunctionCall(ChannelContext $c): void
{
    $c->hasPermission('order:read') && $c->hasPermission('non-existing-permission!');
}
