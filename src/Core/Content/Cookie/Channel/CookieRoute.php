<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Channel;

use Contena\Core\Content\Cookie\CookieException;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Util\UtilException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class CookieRoute extends AbstractCookieRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProvider $cookieProvider,
    ) {
    }

    public function getDecorated(): AbstractCookieRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/cookie-groups', name: 'channel-api.cookie.groups', methods: [Request::METHOD_GET])]
    public function getCookieGroups(Request $request, ChannelContext $channelContext): CookieRouteResponse
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups($request, $channelContext);
        $hash = $this->generateCookieConfigurationHash($cookieGroups);
        $this->setCookieConfigHashValue($cookieGroups, $hash);

        return new CookieRouteResponse($cookieGroups, $hash, $channelContext->getLanguageId());
    }

    /**
     * We use explicit properties to make hash generation robust against object extensions.
     */
    private function generateCookieConfigurationHash(CookieGroupCollection $cookieGroups): string
    {
        $hashData = [];

        $groups = array_values($cookieGroups->getElements());
        usort($groups, static function (CookieGroup $a, CookieGroup $b): int {
            return strcmp($a->getTechnicalName(), $b->getTechnicalName());
        });

        foreach ($groups as $cookieGroup) {
            $groupData = [
                'technicalName' => $cookieGroup->getTechnicalName(),
                'isRequired' => $cookieGroup->isRequired,
                'description' => $cookieGroup->description ?? null,
                'value' => $cookieGroup->value ?? null,
                'expiration' => $cookieGroup->expiration ?? null,
                'name' => $cookieGroup->name,
                'cookie' => $cookieGroup->getCookie(),
            ];

            $groupData['entries'] = null;
            $cookieEntries = $cookieGroup->getEntries();
            if ($cookieEntries !== null) {
                $entries = array_values($cookieEntries->getElements());
                usort($entries, static function (CookieEntry $a, CookieEntry $b): int {
                    return strcmp($a->cookie, $b->cookie);
                });

                $entriesData = [];
                foreach ($entries as $cookieEntry) {
                    $entriesData[] = [
                        'cookie' => $cookieEntry->cookie,
                        'value' => $cookieEntry->value ?? null,
                        'expiration' => $cookieEntry->expiration ?? null,
                        'name' => $cookieEntry->name ?? null,
                        'description' => $cookieEntry->description ?? null,
                        'hidden' => $cookieEntry->hidden,
                    ];
                }
                $groupData['entries'] = $entriesData;
            }

            $hashData[] = $groupData;
        }

        try {
            return Hasher::hash($hashData);
        } catch (UtilException $e) {
            throw CookieException::hashGenerationFailed('Cookie configuration processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Sets the cookie-config-hash entry value to the generated hash for output purposes.
     */
    private function setCookieConfigHashValue(CookieGroupCollection $cookieGroups, string $hash): void
    {
        $cookie = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get(CookieProvider::COOKIE_ENTRY_CONFIG_HASH_COOKIE);
        if ($cookie) {
            $cookie->value = $hash;
        }
    }
}
