<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Extension;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Language\Channel\LanguageRouteResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LanguageRouteResponse>
 */
final class LanguageRouteExtension extends Extension
{
    public const NAME = 'language-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly ChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
