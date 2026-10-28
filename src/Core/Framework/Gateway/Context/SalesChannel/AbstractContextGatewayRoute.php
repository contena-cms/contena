<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
abstract class AbstractContextGatewayRoute
{
    abstract public function getDecorated(): AbstractContextGatewayRoute;

}
