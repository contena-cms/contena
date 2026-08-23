<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class MemberValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<MemberEntity|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== MemberEntity::class) {
            return;
        }

        $loginRequired = $request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED);

        if ($loginRequired !== true && $loginRequired !== 'true') {
            $route = $request->attributes->get('_route');

            throw MemberException::missingRouteAnnotation('LoginRequired', $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof ChannelContext) {
            $route = $request->attributes->get('_route');

            throw MemberException::missingRouteChannel($route);
        }

        yield $context->getMember();
    }
}
