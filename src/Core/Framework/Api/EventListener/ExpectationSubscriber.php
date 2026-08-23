<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-type PluginData array{'composerName': string, 'active': bool, 'version': string}
 */
class ExpectationSubscriber implements EventSubscriberInterface
{
    private const array CONTENA_CORE_PACKAGES = [
        'contena/platform',
        'contena/core',
        'contena/administration',
        'contena/elasticsearch',
        'contena/storefront',
    ];

    /**
     * @internal
     *
     * @param list<PluginData> $plugins
     */
    public function __construct(
        private readonly string $contenaVersion,
        private readonly array $plugins
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['checkExpectations', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
        ];
    }

    public function checkExpectations(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE)) {
            return;
        }

        /** @var list<string> $scope */
        $scope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(ApiRouteScope::ID, $scope, true)) {
            return;
        }

        // The failure messages disclose installed package versions, so the header is only evaluated on routes
        // that validated a token.
        if (!$request->attributes->get('auth_required', true)) {
            if ((string) $request->headers->get(PlatformRequest::HEADER_EXPECT_PACKAGES) === '') {
                return;
            }

            throw ApiException::expectationNotSupported();
        }

        $expectations = $this->checkPackages($request);

        if ($expectations !== []) {
            throw ApiException::expectationFailed($expectations);
        }
    }

    /**
     * @return list<string>
     */
    private function checkPackages(Request $request): array
    {
        // ct/plugin1:~6.1,ct/plugin2:~6.1
        $extensionConstraints = array_filter(explode(',', (string) $request->headers->get(PlatformRequest::HEADER_EXPECT_PACKAGES)));
        if ($extensionConstraints === []) {
            return [];
        }

        $plugins = $this->getIndexedPackages();

        $fails = [];

        foreach ($extensionConstraints as $extension) {
            $explode = explode(':', $extension);
            if (\count($explode) !== 2) {
                $fails[] = \sprintf('Got invalid string: "%s"', $extension);

                continue;
            }

            $name = $explode[0];
            $constraint = $explode[1];

            if (isset($plugins[$name])) {
                $installedVersion = $plugins[$name];
            } else {
                try {
                    $installedVersion = InstalledVersions::getPrettyVersion($name);
                } catch (\OutOfBoundsException) {
                    $fails[] = \sprintf('Requested package: %s is not available', $name);

                    continue;
                }

                if (\in_array($name, self::CONTENA_CORE_PACKAGES, true)) {
                    $installedVersion = $this->contenaVersion;
                }
            }

            if ($installedVersion === null) {
                // should never happen, but phpstan would complain otherwise
                continue;
            }

            if (Semver::satisfies($installedVersion, $constraint)) {
                continue;
            }

            $fails[] = \sprintf('Version constraint for %s is failed. Installed is: %s', $name, $installedVersion);
        }

        return $fails;
    }

    /**
     * Plugins are not in the InstalledPackages file until now
     *
     * @return array<string, string>
     */
    private function getIndexedPackages(): array
    {
        $versions = [];

        foreach ($this->plugins as $plugin) {
            if (!$plugin['active']) {
                continue;
            }

            $versions[$plugin['composerName']] = $plugin['version'];
        }

        return $versions;
    }
}
