<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends Extension<bool>
 *
 * @codeCoverageIgnore
 */
final class CacheHashRequiredExtension extends Extension
{
    public const NAME = 'cache-hash.required';

}
