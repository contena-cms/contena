<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Registration;

use Psr\Http\Message\RequestInterface;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
interface AppHandshakeInterface
{
    public function assembleRequest(): RequestInterface;

    public function fetchAppProof(): string;
}
