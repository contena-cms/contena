<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('framework')]
class ContextValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<Context>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== Context::class) {
            return;
        }

        yield $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT)
            ?? $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
    }
}
