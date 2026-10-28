<?php declare(strict_types=1);

use Contena\Core\System\SalesChannel\SalesChannelContext;

function invalidAclInFunctionCall(SalesChannelContext $c): void
{
    $c->hasPermission('order:read') && $c->hasPermission('non-existing-permission!');
}
