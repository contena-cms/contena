<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class UserValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<UserEntity|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== UserEntity::class) {
            return;
        }

        $loginRequired = $request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED);

        if ($loginRequired !== true) {
            $route = $request->attributes->get('_route');

            throw UserException::missingRouteAnnotation('LoginRequired', $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof ChannelContext) {
            $route = $request->attributes->get('_route');

            throw UserException::missingRouteChannel($route);
        }

        yield $context->getUser();
    }
}
