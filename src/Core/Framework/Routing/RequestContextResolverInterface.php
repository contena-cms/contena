<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

interface RequestContextResolverInterface
{
    public function resolve(Request $request): void;
}
