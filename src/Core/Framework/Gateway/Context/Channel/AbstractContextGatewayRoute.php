<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Channel;

use Symfony\Component\HttpFoundation\Request;

abstract class AbstractContextGatewayRoute
{
    abstract public function getDecorated(): AbstractContextGatewayRoute;

}
