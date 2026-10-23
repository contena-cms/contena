<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

interface BlogGatewayInterface
{
    /**
     * @param array<string> $ids
     */
    public function get(array $ids, ChannelContext $context): BlogCollection;
}
