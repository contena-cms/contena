<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Telemetry;

use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the coarse application `area` of a main HTTP request.
 *
 * The `api.action.sync` route is special-cased because its load/performance profile requires separate instrumentation.
 *
 * Everything else is classified by route scope.
 *
 * Bounded output set (closed match, `other` as default), so the consuming metric labels may use `policy: open`.
 *
 * Known outputs: frontend, channel-api, admin-api, administration, sync-api, other.
 *
 * @internal
 *
 * @final
 */
class AreaResolver
{
    public function resolve(Request $request): string
    {
        $route = (string) $request->attributes->get('_route', '');

        // sync should map to separate area as it's response duration will be an outlier in the most cases
        if ($route === 'api.action.sync') {
            return 'sync-api';
        }

        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        return match (true) {
            \in_array(ChannelApiRouteScope::ID, $scopes, true) => 'channel-api',
            \in_array(ApiRouteScope::ID, $scopes, true) => 'admin-api',
            // Frontend/Administration route scope classes are not always present, using string literals
            \in_array('frontend', $scopes, true) => 'frontend',
            \in_array('administration', $scopes, true) => 'administration',
            default => 'other',
        };
    }
}
