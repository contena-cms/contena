<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

interface RequestContextResolverInterface
{
    public function resolve(Request $request): void;
}
