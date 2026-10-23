<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Snippet\Channel\SnippetRouteResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SnippetRouteResponse>
 */
final class SnippetRouteExtension extends Extension
{
    public const NAME = 'snippet-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
    ) {
    }
}
