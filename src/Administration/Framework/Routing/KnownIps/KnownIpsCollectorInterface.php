<?php declare(strict_types=1);

namespace Contena\Administration\Framework\Routing\KnownIps;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

interface KnownIpsCollectorInterface
{
    /**
     * @return array<string, string>
     */
    public function collectIps(Request $request): array;
}
