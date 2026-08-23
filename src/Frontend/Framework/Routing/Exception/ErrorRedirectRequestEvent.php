<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\Exception;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Component\HttpFoundation\Request;

class ErrorRedirectRequestEvent implements ContenaEvent
{
    public function __construct(
        private readonly Request $request,
        private readonly \Throwable $exception,
        private readonly Context $context,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
