<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<Response>
 */
final class DownloadRouteExtension extends Extension
{
    public const NAME = 'download-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
