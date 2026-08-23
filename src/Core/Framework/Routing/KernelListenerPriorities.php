<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

final class KernelListenerPriorities
{
    public const int KERNEL_REQUEST_EVENT_PRIORITY_TENANT_RESOLVE = 127;

    public const int KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_PRE = -1;

    public const int KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE = -2;

    public const int KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_POST = -3;

    public const int KERNEL_CONTROLLER_EVENT_STORE_API_DOMAIN_RESOLVE = -5;

    public const int KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE = -9;

    public const int KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE = -10;

    public const int KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST = -11;

    public const int KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_PRE = -19;

    public const int KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE = -20;

    public const int KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST = -21;

    private function __construct()
    {
    }
}
