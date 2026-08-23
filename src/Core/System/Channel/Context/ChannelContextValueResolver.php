<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ChannelContextValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<ChannelContext>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== ChannelContext::class) {
            return;
        }

        yield $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
    }
}
