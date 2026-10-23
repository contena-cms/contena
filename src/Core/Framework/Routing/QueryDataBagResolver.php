<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Validation\DataBag\QueryDataBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class QueryDataBagResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<QueryDataBag>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== QueryDataBag::class) {
            return;
        }

        yield new QueryDataBag($request->query->all());
    }
}
