<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Twig;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig\Environment;

#[Package('discovery')]
class ErrorTemplateResolver
{
    /**
     * @internal
     */
    public function __construct(
        protected Environment $twig,
    ) {
    }

}
