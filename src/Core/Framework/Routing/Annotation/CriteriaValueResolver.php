<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Annotation;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CriteriaValueResolver implements ValueResolverInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    /**
     * @return \Generator<Criteria>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== Criteria::class) {
            return;
        }

        $entity = $request->attributes->getString(PlatformRequest::ATTRIBUTE_ENTITY);
        if ($entity === '') {
            $route = $request->attributes->get('_route');

            throw RoutingException::missingRouteAttribute('default "_entity" value', $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            $route = $request->attributes->get('_route');

            throw RoutingException::missingRouteAttribute('context', $route);
        }

        yield $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->registry->getByEntityName($entity),
            $context
        );
    }
}
