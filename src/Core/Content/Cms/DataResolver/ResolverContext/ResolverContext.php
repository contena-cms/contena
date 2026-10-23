<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\ResolverContext;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ResolverContext
{
    public function __construct(
        private readonly ChannelContext $context,
        private readonly Request $request
    ) {
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
