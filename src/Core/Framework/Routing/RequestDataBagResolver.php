<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class RequestDataBagResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<RequestDataBag>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== RequestDataBag::class) {
            return;
        }

        yield new RequestDataBag($request->request->all());
    }
}
